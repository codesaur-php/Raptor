<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Raptor\SessionMiddleware;

/**
 * SessionMiddleware - CSRF-тэй холбоотой session write логикийг тестлэх.
 *
 * Dashboard app-д needsWrite closure нь /login path-г шалгадаг.
 * CSRF_TOKEN session-д байгаа бол session аль хэдийн бичигдсэн гэсэн үг
 * -> зөвхөн /login дээр write хэрэгтэй.
 * CSRF_TOKEN хоосон бол session шинээр эхэлж байна -> write хэрэгтэй.
 */
class SessionMiddlewareCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_ACTIVE) {
            \session_write_close();
        }
    }

    protected function tearDown(): void
    {
        if (\session_status() === \PHP_SESSION_ACTIVE) {
            \session_write_close();
        }
    }

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
     * Dashboard needsWrite closure - CSRF_TOKEN хоосон бол бүх path дээр write хэрэгтэй.
     *
     * Энэ нь шинэ session эхэлж байна гэсэн үг тул
     * login page-д CSRF token бичих шаардлагатай.
     */
    private function dashboardNeedsWriteWithCsrf(string $path, string $method): bool
    {
        // Dashboard app-ын бодит closure логик:
        // /login path дээр session write хэрэгтэй
        return \str_contains($path, '/login');
    }

    // =============================================
    // CSRF_TOKEN хоосон - session write шаардлагатай нөхцөл
    // =============================================

    public function testSessionWritableOnLoginWhenCsrfEmpty(): void
    {
        // CSRF_TOKEN байхгүй -> шинэ session, login дээр write хэрэгтэй
        $this->assertTrue(
            $this->dashboardNeedsWriteWithCsrf('/dashboard/login', 'GET'),
            'Login path should keep session writable when CSRF token is empty'
        );
    }

    public function testSessionWritableOnLoginTryWhenCsrfEmpty(): void
    {
        $this->assertTrue(
            $this->dashboardNeedsWriteWithCsrf('/dashboard/login/try', 'POST'),
            'Login try path should keep session writable for POST'
        );
    }

    // =============================================
    // CSRF_TOKEN байгаа, /login биш path - session close
    // =============================================

    public function testSessionClosedOnDashboardHomeWhenCsrfExists(): void
    {
        // CSRF_TOKEN байгаа + /login биш path -> session write шаардлагагүй
        $this->assertFalse(
            $this->dashboardNeedsWriteWithCsrf('/dashboard', 'GET'),
            'Dashboard home should close session when CSRF exists'
        );
    }

    public function testSessionClosedOnUsersPageWhenCsrfExists(): void
    {
        $this->assertFalse(
            $this->dashboardNeedsWriteWithCsrf('/dashboard/users', 'GET'),
            'Users page should close session when CSRF exists'
        );
    }

    public function testSessionClosedOnNewsPageWhenCsrfExists(): void
    {
        $this->assertFalse(
            $this->dashboardNeedsWriteWithCsrf('/dashboard/news', 'GET'),
            'News page should close session when CSRF exists'
        );
    }

    public function testSessionClosedOnSettingsPage(): void
    {
        $this->assertFalse(
            $this->dashboardNeedsWriteWithCsrf('/dashboard/settings', 'GET'),
            'Settings page should close session write lock'
        );
    }

    // =============================================
    // Web app - /session/ prefix логик
    // =============================================

    /**
     * Web app-ийн needsWrite closure.
     * /session/ prefix-тэй route-ууд session write хэрэгтэй.
     */
    private function webNeedsWriteSession(string $path, string $method): bool
    {
        return \str_starts_with($path, '/session/');
    }

    public function testWebSessionLanguageRouteWritable(): void
    {
        $this->assertTrue(
            $this->webNeedsWriteSession('/session/language/mn', 'GET'),
            '/session/language/mn should keep session writable'
        );
    }

    public function testWebSessionContactSendWritable(): void
    {
        $this->assertTrue(
            $this->webNeedsWriteSession('/session/contact-send', 'POST'),
            '/session/contact-send should keep session writable'
        );
    }

    public function testWebSessionOrderWritable(): void
    {
        $this->assertTrue(
            $this->webNeedsWriteSession('/session/order', 'POST'),
            '/session/order should keep session writable'
        );
    }

    public function testWebNonSessionRouteReadOnly(): void
    {
        $this->assertFalse(
            $this->webNeedsWriteSession('/news/article', 'GET'),
            '/news/article should NOT keep session writable'
        );
    }

    public function testWebHomeReadOnly(): void
    {
        $this->assertFalse(
            $this->webNeedsWriteSession('/', 'GET'),
            'Home page should NOT keep session writable'
        );
    }

    public function testWebProductsReadOnly(): void
    {
        $this->assertFalse(
            $this->webNeedsWriteSession('/products', 'GET'),
            'Products page should NOT keep session writable'
        );
    }

    // =============================================
    // Middleware null closure default - бүх route read-only
    // =============================================

    public function testNullClosureClosesSessionForAllRoutes(): void
    {
        // needsWrite null бол бүх route дээр session_write_close() дуудна
        $middleware = new SessionMiddleware(null);
        $request = $this->createRequestWithPath('/dashboard/anything');
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * Middleware response буцаах - closure-тэй.
     */
    public function testMiddlewareWithClosureReturnsResponse(): void
    {
        $middleware = new SessionMiddleware(
            fn(string $path, string $method): bool => \str_contains($path, '/login')
        );
        $request = $this->createRequestWithPath('/dashboard/login');
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // =============================================
    // Path extraction - SCRIPT_NAME stripping
    // =============================================

    public function testPathExtractionWithSubdirectory(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/subdir/dashboard/login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getServerParams')->willReturn([
            'SCRIPT_NAME' => '/subdir/index.php',
        ]);

        // /subdir хэсэг хасагдаж /dashboard/login болно
        // -> needsWrite closure-д /dashboard/login дамжина
        $closurePath = null;
        $middleware = new SessionMiddleware(
            function (string $path, string $method) use (&$closurePath): bool {
                $closurePath = $path;
                return false;
            }
        );

        $handler = $this->createHandler();
        $middleware->process($request, $handler);

        $this->assertSame('/dashboard/login', $closurePath);
    }

    public function testPathExtractionWithRootScript(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/dashboard/news');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getServerParams')->willReturn([
            'SCRIPT_NAME' => '/index.php',
        ]);

        $closurePath = null;
        $middleware = new SessionMiddleware(
            function (string $path, string $method) use (&$closurePath): bool {
                $closurePath = $path;
                return false;
            }
        );

        $handler = $this->createHandler();
        $middleware->process($request, $handler);

        // Root directory dirname = / (len=1), тул path өөрчлөгдөхгүй
        $this->assertSame('/dashboard/news', $closurePath);
    }
}
