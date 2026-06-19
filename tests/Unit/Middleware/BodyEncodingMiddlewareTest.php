<?php

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Raptor\BodyEncodingMiddleware;

/**
 * BodyEncodingMiddleware unit test.
 *
 * Клиент тал (csrfFetch) mod_security WAF-ийг тойрохын тулд form талбаруудыг
 * base64-аар кодолж X-Body-Encoding: base64 header-тэй илгээдэг. Энэ middleware
 * тэр үед parsedBody-гийн string утгуудыг буцааж decode хийх ёстой.
 */
class BodyEncodingMiddlewareTest extends TestCase
{
    private BodyEncodingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new BodyEncodingMiddleware();
    }

    /**
     * @param array<string,mixed> $parsedBody
     */
    private function createRequest(string $encodingHeader, array $parsedBody): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $n) => $n === 'X-Body-Encoding' ? $encodingHeader : '');
        $request->method('getParsedBody')->willReturn($parsedBody);
        // base64 branch-д header-ээ арилгахаар withoutHeader() дуудагдана
        $request->method('withoutHeader')->willReturnSelf();
        // withParsedBody буцаасан утгыг барих; дараа нь withoutHeader гинжлэгдэнэ
        $request->method('withParsedBody')->willReturnCallback(function ($body) {
            $clone = $this->createMock(ServerRequestInterface::class);
            $clone->method('getParsedBody')->willReturn($body);
            $clone->method('withoutHeader')->willReturnSelf();
            return $clone;
        });
        return $request;
    }

    /**
     * handle()-д ирсэн request-ийн parsedBody-г барих handler.
     */
    private function createHandler(?array &$seenBody): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $req) use (&$seenBody, $response) {
                $seenBody = $req->getParsedBody();
                return $response;
            }
        );
        return $handler;
    }

    public function testDecodesBase64FieldsWhenHeaderPresent(): void
    {
        $body = [
            'title'   => \base64_encode('Сайн байна уу'),
            'content' => \base64_encode('<a href="x">XSS-ish</a>'),
        ];
        $seen = null;
        $this->middleware->process($this->createRequest('base64', $body), $this->createHandler($seen));

        $this->assertSame('Сайн байна уу', $seen['title']);
        $this->assertSame('<a href="x">XSS-ish</a>', $seen['content']);
    }

    public function testDecodesNestedArrays(): void
    {
        $body = ['localized' => ['mn' => ['title' => \base64_encode('Гарчиг')]]];
        $seen = null;
        $this->middleware->process($this->createRequest('base64', $body), $this->createHandler($seen));

        $this->assertSame('Гарчиг', $seen['localized']['mn']['title']);
    }

    public function testNoHeaderLeavesBodyUntouched(): void
    {
        $body = ['title' => \base64_encode('Гарчиг')];
        $seen = null;
        $this->middleware->process($this->createRequest('', $body), $this->createHandler($seen));

        // Header байхгүй тул decode хийхгүй - анхны (кодлогдсон) утга хэвээр
        $this->assertSame(\base64_encode('Гарчиг'), $seen['title']);
    }

    public function testInvalidBase64KeptAsIs(): void
    {
        // Strict base64-д тохирохгүй утга - анхны хэвээр үлдэнэ (corrupt хийхгүй)
        $body = ['x' => '!!!not-base64!!!'];
        $seen = null;
        $this->middleware->process($this->createRequest('base64', $body), $this->createHandler($seen));

        $this->assertSame('!!!not-base64!!!', $seen['x']);
    }

    public function testContentWithInlineBase64ImageNotCorrupted(): void
    {
        // content дотор data-URI base64 зураг агуулсан тохиолдол. Гадна талын
        // нэг давхарга encode -> server нэг удаа decode -> дотор base64 яг хэвээр.
        // (Давхар decode хийдэггүй тул эвдрэхгүй.)
        $original = '<p>Зураг:</p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==">';
        $body = ['content' => \base64_encode($original)];
        $seen = null;
        $this->middleware->process($this->createRequest('base64', $body), $this->createHandler($seen));

        $this->assertSame($original, $seen['content'], 'Inline base64 image must survive byte-for-byte');
        $this->assertStringContainsString('data:image/png;base64,iVBOR', $seen['content']);
    }

    public function testRoundTripUtf8(): void
    {
        // Client (b64EncodeUnicode) -> Server decode round-trip симуляц.
        // btoa(utf8 bytes) === PHP base64_encode(utf8 string)
        $original = 'Монгол текст 🦖 <script>alert(1)</script>';
        $body = ['content' => \base64_encode($original)];
        $seen = null;
        $this->middleware->process($this->createRequest('base64', $body), $this->createHandler($seen));

        $this->assertSame($original, $seen['content']);
    }

    public function testEncodingHeaderIsStrippedAfterUse(): void
    {
        // Decode хийсний дараа X-Body-Encoding header арилах ёстой
        // (consume-after-use; дотоод re-dispatch үед давхар decode-оос сэргийлнэ).
        $stripped = null;
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $n) => $n === 'X-Body-Encoding' ? 'base64' : '');
        $request->method('getParsedBody')->willReturn(['title' => \base64_encode('x')]);
        $request->method('withParsedBody')->willReturnCallback(function ($body) use (&$stripped) {
            $clone = $this->createMock(ServerRequestInterface::class);
            $clone->method('getParsedBody')->willReturn($body);
            $clone->method('withoutHeader')->willReturnCallback(function (string $h) use (&$stripped, $clone) {
                $stripped = $h;
                return $clone;
            });
            return $clone;
        });

        $seen = null;
        $this->middleware->process($request, $this->createHandler($seen));

        $this->assertSame('x', $seen['title']);
        $this->assertSame('X-Body-Encoding', $stripped, 'Encoding header-ийг арилгасан байх ёстой');
    }
}
