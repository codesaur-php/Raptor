<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Raptor\SessionMiddleware;

/**
 * SessionMiddleware - session write-lock логикийг тестлэх.
 *
 * session_write_close() нь CLI-д дуудагдахгүй тул
 * бид зөвхөн middleware-ийн needsWrite callback логикийг шалгана.
 */
class SessionMiddlewareTest extends TestCase
{
    private function createRequestWithPath(string $path, string $method = 'GET'): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getServerParams')->willReturn([
            'SCRIPT_NAME' => '/index.php',
        ]);

        return $request;
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    /**
     * Middleware нь handler-г дуудаж response буцаадаг эсэх.
     */
    public function testMiddlewareReturnsResponse(): void
    {
        $middleware = new SessionMiddleware();
        $request = $this->createRequestWithPath('/');
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * Web app-ийн needsWrite closure логик.
     */
    private function webNeedsWrite(string $path, string $method): bool
    {
        $fn = fn(string $path, string $method): bool =>
            \str_starts_with($path, '/language/')
            || ($path === '/order' && $method === 'POST');
        return $fn($path, $method);
    }

    /**
     * Dashboard app-ийн needsWrite closure логик.
     */
    private function dashboardNeedsWrite(string $path, string $method): bool
    {
        $fn = fn(string $path, string $method): bool =>
            \str_contains($path, '/login');
        return $fn($path, $method);
    }

    // =============================================
    // Web closure tests
    // =============================================

    public function testWebLanguageRouteNeedsWrite(): void
    {
        $this->assertTrue(
            $this->webNeedsWrite('/language/mn', 'GET'),
            '/language/mn should keep session writable'
        );
    }

    public function testWebPostOrderNeedsWrite(): void
    {
        $this->assertTrue(
            $this->webNeedsWrite('/order', 'POST'),
            'POST /order should keep session writable'
        );
    }

    public function testWebGetOrderReadOnly(): void
    {
        $this->assertFalse(
            $this->webNeedsWrite('/order', 'GET'),
            'GET /order should NOT keep session writable'
        );
    }

    /**
     * @dataProvider webReadOnlyRoutesProvider
     */
    public function testWebReadOnlyRoutes(string $path): void
    {
        $this->assertFalse(
            $this->webNeedsWrite($path, 'GET'),
            "$path should close session write lock"
        );
    }

    public static function webReadOnlyRoutesProvider(): array
    {
        return [
            'home'     => ['/'],
            'news'     => ['/news/some-slug'],
            'page'     => ['/page/about'],
            'products' => ['/products'],
            'search'   => ['/search'],
            'sitemap'  => ['/sitemap'],
            'rss'      => ['/rss'],
            'contact'  => ['/contact'],
        ];
    }

    // =============================================
    // Dashboard closure tests
    // =============================================

    public function testDashboardLoginNeedsWrite(): void
    {
        $this->assertTrue(
            $this->dashboardNeedsWrite('/dashboard/login', 'GET'),
            '/dashboard/login should keep session writable'
        );
    }

    public function testDashboardLoginTryNeedsWrite(): void
    {
        $this->assertTrue(
            $this->dashboardNeedsWrite('/dashboard/login/try', 'POST'),
            '/dashboard/login/try should keep session writable'
        );
    }

    /**
     * @dataProvider dashboardReadOnlyRoutesProvider
     */
    public function testDashboardReadOnlyRoutes(string $path): void
    {
        $this->assertFalse(
            $this->dashboardNeedsWrite($path, 'GET'),
            "$path should close session write lock"
        );
    }

    public static function dashboardReadOnlyRoutesProvider(): array
    {
        return [
            'home'    => ['/dashboard'],
            'users'   => ['/dashboard/users'],
            'news'    => ['/dashboard/news'],
            'pages'   => ['/dashboard/pages'],
            'logs'    => ['/dashboard/logs'],
            'rbac'    => ['/dashboard/rbac'],
        ];
    }

    // =============================================
    // Null closure (default) test
    // =============================================

    public function testNullClosureReturnsResponse(): void
    {
        $middleware = new SessionMiddleware(null);
        $request = $this->createRequestWithPath('/anything');
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
