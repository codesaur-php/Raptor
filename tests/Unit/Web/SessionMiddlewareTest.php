<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /*     */
    #[DataProvider('webReadOnlyRoutesProvider')]
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

    /*     */
    #[DataProvider('dashboardReadOnlyRoutesProvider')]
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

    // =============================================
    // Session cookie аюулгүй байдлын flag-ууд
    // (session_set_cookie_params-ийг CLI-д ажиллуулж болохгүй тул
    //  source-code түвшинд flag-ууд байгааг баталгаажуулна)
    // =============================================

    private static function source(): string
    {
        return \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/SessionMiddleware.php'
        );
    }

    public function testCookieHttpOnly(): void
    {
        $this->assertMatchesRegularExpression(
            "/'httponly'\s*=>\s*true/",
            self::source(),
            'Session cookie must be HttpOnly (XSS session theft mitigation)'
        );
    }

    public function testCookieSameSiteLax(): void
    {
        $this->assertMatchesRegularExpression(
            "/'samesite'\s*=>\s*'Lax'/",
            self::source(),
            'Session cookie must set SameSite=Lax (CSRF mitigation)'
        );
    }

    public function testCookieSecureFollowsHttps(): void
    {
        $source = self::source();

        // secure flag нь HTTPS илрүүлэлтээс хамаарна (hardcode true/false биш)
        $this->assertMatchesRegularExpression(
            "/'secure'\s*=>\s*\\\$isHttps/",
            $source,
            'Session cookie secure flag must follow HTTPS detection'
        );
        // HTTPS илрүүлэлт нь HTTPS, порт 443, proxy-ийн X-Forwarded-Proto-г шалгана
        $this->assertStringContainsString('HTTPS', $source);
        $this->assertStringContainsString('HTTP_X_FORWARDED_PROTO', $source);
    }
}
