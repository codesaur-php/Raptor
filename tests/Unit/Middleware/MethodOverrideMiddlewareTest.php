<?php

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Dashboard\MethodOverrideMiddleware;

/**
 * MethodOverrideMiddleware unit test.
 *
 * Зарим shared hosting (cPanel/LiteSpeed/mod_security) PUT/PATCH/DELETE verb-ийг
 * server түвшинд блоклодог тул клиент тал тэдгээрийг POST-оор илгээж,
 * X-HTTP-Method-Override header-аар жинхэнэ method-оо дамжуулна. Энэ middleware
 * route matching хийгдэхээс өмнө method-ийг сэргээх ёстой.
 *
 * Аюулгүй байдал: зөвхөн POST дээр, зөвхөн PUT/PATCH/DELETE руу override.
 */
class MethodOverrideMiddlewareTest extends TestCase
{
    private MethodOverrideMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new MethodOverrideMiddleware();
    }

    /**
     * Mock handler - handle()-д ирсэн request-ийн method-ийг барьж авна.
     */
    private function createHandler(?string &$seenMethod): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $req) use (&$seenMethod, $response) {
                $seenMethod = $req->getMethod();
                return $response;
            }
        );
        return $handler;
    }

    private function createRequest(string $method, string $override = ''): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $name) => $name === 'X-HTTP-Method-Override' ? $override : '');
        // withMethod() нь шинэ (overridden) request буцаана; дараа нь түүн дээр
        // withoutHeader() гинжлэгдэх тул clone нь өөрийгөө буцаана.
        $request->method('withMethod')->willReturnCallback(function (string $m) {
            $clone = $this->createMock(ServerRequestInterface::class);
            $clone->method('getMethod')->willReturn($m);
            $clone->method('withoutHeader')->willReturnSelf();
            return $clone;
        });
        return $request;
    }

    public function testPostWithPutOverrideBecomesPut(): void
    {
        $seen = null;
        $this->middleware->process($this->createRequest('POST', 'PUT'), $this->createHandler($seen));
        $this->assertSame('PUT', $seen);
    }

    public function testPostWithDeleteOverrideBecomesDelete(): void
    {
        $seen = null;
        $this->middleware->process($this->createRequest('POST', 'DELETE'), $this->createHandler($seen));
        $this->assertSame('DELETE', $seen);
    }

    public function testPostWithPatchOverrideBecomesPatch(): void
    {
        $seen = null;
        $this->middleware->process($this->createRequest('POST', 'patch'), $this->createHandler($seen));
        $this->assertSame('PATCH', $seen, 'Override нь case-insensitive байх ёстой');
    }

    public function testPostWithoutOverrideStaysPost(): void
    {
        $seen = null;
        $this->middleware->process($this->createRequest('POST', ''), $this->createHandler($seen));
        $this->assertSame('POST', $seen);
    }

    public function testPostWithGetOverrideIsIgnored(): void
    {
        // GET руу override зөвшөөрөхгүй - CsrfMiddleware-ийн safe-method bypass-аар
        // CSRF шалгалтыг тойрох эрсдэлээс сэргийлнэ.
        $seen = null;
        $this->middleware->process($this->createRequest('POST', 'GET'), $this->createHandler($seen));
        $this->assertSame('POST', $seen);
    }

    public function testGetRequestWithOverrideIsIgnored(): void
    {
        // Зөвхөн POST дээр override үйлчилнэ.
        $seen = null;
        $this->middleware->process($this->createRequest('GET', 'DELETE'), $this->createHandler($seen));
        $this->assertSame('GET', $seen);
    }

    public function testOverrideHeaderIsStrippedAfterUse(): void
    {
        // Verb сэргээсний дараа X-HTTP-Method-Override header арилах ёстой
        // (consume-after-use).
        $stripped = null;
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $n) => $n === 'X-HTTP-Method-Override' ? 'PUT' : '');
        $request->method('withMethod')->willReturnCallback(function (string $m) use (&$stripped) {
            $clone = $this->createMock(ServerRequestInterface::class);
            $clone->method('getMethod')->willReturn($m);
            $clone->method('withoutHeader')->willReturnCallback(function (string $h) use (&$stripped, $clone) {
                $stripped = $h;
                return $clone;
            });
            return $clone;
        });

        $seen = null;
        $this->middleware->process($request, $this->createHandler($seen));

        $this->assertSame('PUT', $seen);
        $this->assertSame('X-HTTP-Method-Override', $stripped, 'Override header-ийг арилгасан байх ёстой');
    }
}
