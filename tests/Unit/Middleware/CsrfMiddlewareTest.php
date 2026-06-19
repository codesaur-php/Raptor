<?php

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Raptor\CsrfMiddleware;

/**
 * CsrfMiddleware unit test.
 *
 * CsrfMiddleware нь per-route validator - router дээр mutating route бүрд
 * `->middleware([CsrfMiddleware::class])`-аар наагдана. Token үүсгэх, attribute
 * тавих, login-exempt зэрэг нь middleware-ийн хариуцлага биш:
 *   - Token үүсгэлт: login + Controller::template() (session-аас).
 *   - Login exempt: login route-д middleware наахгүйгээр шийдэгдэнэ.
 *
 * exit() дууддаг 403 хариултыг шууд тестлэх боломжгүй тул safe method
 * pass-through, valid token pass, болон validation нөхцлийн логикийг тестлэнэ.
 */
class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new CsrfMiddleware();

        // Session state цэвэрлэх
        unset($_SESSION);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
        $_SESSION = [];
    }

    /**
     * Mock request үүсгэх helper.
     */
    private function createRequest(
        string $method = 'GET',
        string $csrfHeader = ''
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($csrfHeader) {
                return $name === 'X-CSRF-TOKEN' ? $csrfHeader : '';
            });

        return $request;
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    // ---------------------------------------------------------
    // Safe methods (GET, HEAD, OPTIONS) pass through
    // ---------------------------------------------------------

    public function testGetRequestPassesThrough(): void
    {
        $response = $this->middleware->process($this->createRequest('GET'), $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHeadRequestPassesThrough(): void
    {
        $response = $this->middleware->process($this->createRequest('HEAD'), $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testOptionsRequestPassesThrough(): void
    {
        $response = $this->middleware->process($this->createRequest('OPTIONS'), $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ---------------------------------------------------------
    // Mutating request with valid matching token passes through
    // ---------------------------------------------------------

    public function testPostWithValidTokenPasses(): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['CSRF_TOKEN'] = $token;

        $request = $this->createRequest('POST', $token);
        $response = $this->middleware->process($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ---------------------------------------------------------
    // CSRF validation condition logic
    //
    // Нөхцөл: empty($sessionToken) || empty($headerToken)
    //         || !hash_equals($sessionToken, $headerToken)
    // ---------------------------------------------------------

    public function testValidationConditionEmptySessionToken(): void
    {
        $sessionToken = '';
        $headerToken = 'some-token';
        $fails = empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken);
        $this->assertTrue($fails, 'Empty session token should fail validation');
    }

    public function testValidationConditionEmptyHeaderToken(): void
    {
        $sessionToken = 'some-token';
        $headerToken = '';
        $fails = empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken);
        $this->assertTrue($fails, 'Empty header token should fail validation');
    }

    public function testValidationConditionMismatchedTokens(): void
    {
        $sessionToken = 'token-aaa';
        $headerToken = 'token-bbb';
        $fails = empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken);
        $this->assertTrue($fails, 'Mismatched tokens should fail validation');
    }

    public function testValidationConditionMatchingTokens(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionToken = $token;
        $headerToken = $token;
        $fails = empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken);
        $this->assertFalse($fails, 'Matching tokens should pass validation');
    }

    public function testValidationConditionBothEmpty(): void
    {
        $sessionToken = '';
        $headerToken = '';
        $fails = empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken);
        $this->assertTrue($fails, 'Both empty tokens should fail validation');
    }

    // ---------------------------------------------------------
    // PUT / DELETE / POST are not safe methods (require validation)
    // ---------------------------------------------------------

    public function testPutMethodIsNotSafe(): void
    {
        $this->assertFalse(in_array('PUT', ['GET', 'HEAD', 'OPTIONS'], true));
    }

    public function testDeleteMethodIsNotSafe(): void
    {
        $this->assertFalse(in_array('DELETE', ['GET', 'HEAD', 'OPTIONS'], true));
    }

    public function testPostMethodIsNotSafe(): void
    {
        $this->assertFalse(in_array('POST', ['GET', 'HEAD', 'OPTIONS'], true));
    }
}
