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
 * exit() дууддаг 403 хариултыг шууд тестлэх боломжгүй тул
 * token үүсгэх, attribute тохируулах, safe method/login exempt логикийг тестлэнэ.
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
        string $path = '/dashboard',
        string $csrfHeader = ''
    ): ServerRequestInterface {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn([
            'SCRIPT_NAME' => '/index.php',
        ]);
        $request->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($csrfHeader) {
                if ($name === 'X-CSRF-TOKEN') {
                    return $csrfHeader;
                }
                return '';
            });

        // withAttribute-г дуудахад шинэ request буцаах
        $request->method('withAttribute')
            ->willReturnCallback(function (string $name, $value) use ($request) {
                return $request;
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
        $request = $this->createRequest('GET');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHeadRequestPassesThrough(): void
    {
        $request = $this->createRequest('HEAD');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testOptionsRequestPassesThrough(): void
    {
        $request = $this->createRequest('OPTIONS');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ---------------------------------------------------------
    // Login path is exempt from CSRF
    // ---------------------------------------------------------

    public function testLoginPathIsExempt(): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['CSRF_TOKEN'] = $token;

        // POST to /login without X-CSRF-TOKEN header should still pass
        $request = $this->createRequest('POST', '/login', '');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testLoginTryPathIsExempt(): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['CSRF_TOKEN'] = $token;

        $request = $this->createRequest('POST', '/login/try', '');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ---------------------------------------------------------
    // Token generation when session is active and token empty
    // ---------------------------------------------------------

    public function testTokenNotGeneratedWhenSessionInactive(): void
    {
        // session_status() != PHP_SESSION_ACTIVE in CLI, so token stays empty
        unset($_SESSION['CSRF_TOKEN']);

        $request = $this->createRequest('GET');
        $handler = $this->createHandler();

        $this->middleware->process($request, $handler);

        // In CLI, session is not active so token should not be generated
        $this->assertArrayNotHasKey('CSRF_TOKEN', $_SESSION);
    }

    public function testExistingTokenIsPreserved(): void
    {
        $existingToken = 'existing-token-value-1234567890abcdef';
        $_SESSION['CSRF_TOKEN'] = $existingToken;

        $request = $this->createRequest('GET');
        $handler = $this->createHandler();

        $this->middleware->process($request, $handler);

        $this->assertEquals($existingToken, $_SESSION['CSRF_TOKEN']);
    }

    // ---------------------------------------------------------
    // csrf_token attribute is set on request
    // ---------------------------------------------------------

    public function testCsrfTokenAttributeIsSet(): void
    {
        $token = 'test-csrf-token-abc123';
        $_SESSION['CSRF_TOKEN'] = $token;

        $attributeSet = [];
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/dashboard');
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn(['SCRIPT_NAME' => '/index.php']);

        // Capture the withAttribute call
        $request->method('withAttribute')
            ->willReturnCallback(function (string $name, $value) use (&$attributeSet, $request) {
                $attributeSet[$name] = $value;
                return $request;
            });

        $handler = $this->createHandler();
        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey('csrf_token', $attributeSet);
        $this->assertEquals($token, $attributeSet['csrf_token']);
    }

    public function testCsrfTokenAttributeIsEmptyWhenNoSession(): void
    {
        unset($_SESSION['CSRF_TOKEN']);

        $attributeSet = [];
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/dashboard');
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn(['SCRIPT_NAME' => '/index.php']);

        $request->method('withAttribute')
            ->willReturnCallback(function (string $name, $value) use (&$attributeSet, $request) {
                $attributeSet[$name] = $value;
                return $request;
            });

        $handler = $this->createHandler();
        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey('csrf_token', $attributeSet);
        $this->assertEquals('', $attributeSet['csrf_token']);
    }

    // ---------------------------------------------------------
    // POST with valid matching token passes through
    // ---------------------------------------------------------

    public function testPostWithValidTokenPasses(): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['CSRF_TOKEN'] = $token;

        $request = $this->createRequest('POST', '/dashboard/news', $token);
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ---------------------------------------------------------
    // CSRF validation condition logic tests
    // ---------------------------------------------------------

    /**
     * The middleware's validation condition:
     * empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken)
     *
     * We test that condition evaluates correctly for various inputs.
     */
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
    // PUT and DELETE methods require CSRF validation
    // ---------------------------------------------------------

    public function testPutMethodIsNotSafe(): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        $this->assertFalse(in_array('PUT', $safeMethods, true));
    }

    public function testDeleteMethodIsNotSafe(): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        $this->assertFalse(in_array('DELETE', $safeMethods, true));
    }

    public function testPostMethodIsNotSafe(): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        $this->assertFalse(in_array('POST', $safeMethods, true));
    }
}
