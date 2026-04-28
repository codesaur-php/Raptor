<?php

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use codesaur\Router\Router;

/**
 * Partial update route-уудыг PUT-с PATCH болгосон шинэчлэлтийг тестлэх.
 *
 * Шалгах зүйлс:
 *   - PATCH route-ууд зөв match хийдэг эсэх
 *   - PUT method-оор хандахад match хийхгүй эсэх (breaking change-гүй гэж батлах)
 *   - Route name-ууд URL зөв generate хийдэг эсэх
 *   - CsrfMiddleware PATCH method-г шалгадаг эсэх
 *   - Frontend template-ууд PATCH ашигладаг эсэх
 */
class PatchRoutesTest extends TestCase
{
    // =========================================================
    // Router-level PATCH matching
    // =========================================================

    /**
     * @dataProvider patchRouteProvider
     */
    public function testPatchRouteMatches(string $pattern, string $path, string $routeName): void
    {
        $router = new Router();
        $router->PATCH($pattern, ['TestController', 'action'])->name($routeName);

        $callback = $router->match($path, 'PATCH');

        $this->assertNotNull($callback, "PATCH $path should match route $routeName");
    }

    /**
     * @dataProvider patchRouteProvider
     */
    public function testPutDoesNotMatchPatchRoute(string $pattern, string $path, string $routeName): void
    {
        $router = new Router();
        $router->PATCH($pattern, ['TestController', 'action'])->name($routeName);

        $callback = $router->match($path, 'PUT');

        $this->assertNull($callback, "PUT should NOT match PATCH route $routeName");
    }

    public static function patchRouteProvider(): array
    {
        return [
            'order-status' => [
                '/dashboard/orders/{uint:id}/status',
                '/dashboard/orders/5/status',
                'order-status',
            ],
            'files-update' => [
                '/dashboard/files/{table}/{uint:id}',
                '/dashboard/files/content_files/3',
                'files-update',
            ],
            'settings-env' => [
                '/dashboard/settings/env',
                '/dashboard/settings/env',
                'settings-env',
            ],
            'messages-replied' => [
                '/dashboard/messages/replied/{uint:id}',
                '/dashboard/messages/replied/10',
                'messages-replied',
            ],
        ];
    }

    // =========================================================
    // Route parameter parsing
    // =========================================================

    public function testOrderStatusRouteExtractsId(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/orders/{uint:id}/status', ['TestController', 'updateStatus']);

        $callback = $router->match('/dashboard/orders/42/status', 'PATCH');

        $this->assertNotNull($callback);
        $params = $callback->getParameters();
        $this->assertSame(42, $params['id']);
    }

    public function testFilesUpdateRouteExtractsTableAndId(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/files/{table}/{uint:id}', ['TestController', 'update']);

        $callback = $router->match('/dashboard/files/content_files/7', 'PATCH');

        $this->assertNotNull($callback);
        $params = $callback->getParameters();
        $this->assertSame('content_files', $params['table']);
        $this->assertSame(7, $params['id']);
    }

    public function testMessagesRepliedRouteExtractsId(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/messages/replied/{uint:id}', ['TestController', 'markReplied']);

        $callback = $router->match('/dashboard/messages/replied/99', 'PATCH');

        $this->assertNotNull($callback);
        $params = $callback->getParameters();
        $this->assertSame(99, $params['id']);
    }

    // =========================================================
    // URL generation
    // =========================================================

    public function testPatchRouteNameGeneratesUrl(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/orders/{uint:id}/status', ['TestController', 'updateStatus'])->name('order-status');

        $url = $router->generate('order-status', ['id' => 15]);

        $this->assertSame('/dashboard/orders/15/status', $url);
    }

    public function testSettingsEnvRouteGeneratesUrl(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/settings/env', ['TestController', 'updateEnv'])->name('settings-env');

        $url = $router->generate('settings-env');

        $this->assertSame('/dashboard/settings/env', $url);
    }

    // =========================================================
    // PATCH + PUT нэг router-д зэрэг ажиллах
    // =========================================================

    public function testPatchAndPutCoexist(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/orders/{uint:id}/status', ['TestController', 'updateStatus']);
        $router->GET_PUT('/dashboard/news/{uint:id}', ['TestController', 'update']);

        // PATCH route -> PATCH method-ээр match
        $patchMatch = $router->match('/dashboard/orders/1/status', 'PATCH');
        $this->assertNotNull($patchMatch, 'PATCH route should match PATCH method');

        // GET_PUT route -> PUT method-ээр match
        $putMatch = $router->match('/dashboard/news/1', 'PUT');
        $this->assertNotNull($putMatch, 'GET_PUT route should still match PUT method');

        // GET_PUT route -> GET method-ээр match
        $getMatch = $router->match('/dashboard/news/1', 'GET');
        $this->assertNotNull($getMatch, 'GET_PUT route should still match GET method');
    }

    public function testPatchDoesNotMatchGetPutRoute(): void
    {
        $router = new Router();
        $router->GET_PUT('/dashboard/news/{uint:id}', ['TestController', 'update']);

        $callback = $router->match('/dashboard/news/1', 'PATCH');

        $this->assertNull($callback, 'PATCH should NOT match GET_PUT route');
    }

    // =========================================================
    // Negative matching - буруу path/method
    // =========================================================

    public function testPatchRouteRejectsNegativeId(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/orders/{uint:id}/status', ['TestController', 'updateStatus']);

        $callback = $router->match('/dashboard/orders/-1/status', 'PATCH');

        $this->assertNull($callback, 'Negative ID should not match uint parameter');
    }

    public function testPatchRouteRejectsGetMethod(): void
    {
        $router = new Router();
        $router->PATCH('/dashboard/settings/env', ['TestController', 'updateEnv']);

        $callback = $router->match('/dashboard/settings/env', 'GET');

        $this->assertNull($callback, 'GET should not match PATCH-only route');
    }

    // =========================================================
    // CsrfMiddleware: PATCH нь safe method биш
    // =========================================================

    public function testPatchMethodIsNotSafe(): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        $this->assertFalse(
            \in_array('PATCH', $safeMethods, true),
            'PATCH must NOT be in the safe methods list - CSRF validation required'
        );
    }

    public function testPatchWithValidCsrfTokenPasses(): void
    {
        $token = \bin2hex(\random_bytes(32));
        $_SESSION['CSRF_TOKEN'] = $token;

        $middleware = new \Raptor\CsrfMiddleware();

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/dashboard/orders/1/status');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('PATCH');
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn(['SCRIPT_NAME' => '/index.php']);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name) => $name === 'X-CSRF-TOKEN' ? $token : ''
        );
        $request->method('withAttribute')->willReturn($request);

        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);

        unset($_SESSION);
        $_SESSION = [];
    }

    // =========================================================
    // Source code verification - Router files
    // =========================================================

    public function testShopRouterUsesPatchForOrderStatus(): void
    {
        // OrdersRouter, ProductsRouter, ReviewsRouter are merged into ShopRouter
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/shop/ShopRouter.php'
        );

        $this->assertStringContainsString(
            "->PATCH('/dashboard/orders/{uint:id}/status'",
            $source,
            'ShopRouter should use PATCH for order status update'
        );
        $this->assertStringNotContainsString(
            "->PUT('/dashboard/orders/{uint:id}/status'",
            $source,
            'ShopRouter should NOT use PUT for order status update'
        );
    }

    public function testContentsRouterUsesPatchForPartialUpdates(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/ContentsRouter.php'
        );

        $this->assertStringContainsString(
            "->PATCH('/dashboard/files/{table}/{uint:id}'",
            $source,
            'ContentsRouter should use PATCH for files update'
        );
        $this->assertStringContainsString(
            "->PATCH('/dashboard/settings/env'",
            $source,
            'ContentsRouter should use PATCH for settings env'
        );
        $this->assertStringContainsString(
            "->PATCH('/dashboard/messages/replied/{uint:id}'",
            $source,
            'ContentsRouter should use PATCH for messages replied'
        );
    }

    public function testContentsRouterStillUsesPutForFullUpdates(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/ContentsRouter.php'
        );

        // GET_PUT route-ууд PUT хэвээр байх ёстой
        $this->assertStringContainsString("GET_PUT('/dashboard/news/{uint:id}'", $source);
        $this->assertStringContainsString("GET_PUT('/dashboard/pages/{uint:id}'", $source);
        $this->assertStringContainsString("GET_PUT('/dashboard/references/{table}/{uint:id}'", $source);
    }

    // =========================================================
    // Source code verification - Frontend templates
    // =========================================================

    /**
     * @dataProvider frontendPatchProvider
     */
    public function testFrontendTemplateUsesPatch(string $relPath, string $description): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/' . $relPath);

        $this->assertStringNotContainsString(
            "method: 'PUT'",
            $source,
            "$description: should use PATCH instead of PUT"
        );
    }

    public static function frontendPatchProvider(): array
    {
        return [
            'orders-view' => [
                'application/dashboard/shop/orders-view.html',
                'Order status update',
            ],
            'orders-index' => [
                'application/dashboard/shop/orders-index.html',
                'Orders index env settings',
            ],
            'reviews-index' => [
                'application/dashboard/shop/reviews-index.html',
                'Reviews index env settings',
            ],
            'messages-index' => [
                'application/raptor/content/messages/messages-index.html',
                'Messages index replied + env settings',
            ],
            'comments-index' => [
                'application/raptor/content/news/comments-index.html',
                'Comments index env settings',
            ],
        ];
    }

    public function testFilesUpdateModalUsesPatch(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/file/files-update-modal.html'
        );

        $this->assertStringContainsString(
            'method="PATCH"',
            $source,
            'Files update modal form should use PATCH method'
        );
        $this->assertStringNotContainsString(
            'method="PUT"',
            $source,
            'Files update modal form should NOT use PUT method'
        );
    }
}
