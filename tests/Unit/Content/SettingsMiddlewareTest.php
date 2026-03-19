<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Raptor\Content\SettingsMiddleware;

/**
 * SettingsMiddleware - settings ачаалах, хэлний код шалгах, fallback логикийг тестлэх.
 *
 * DB-гүй орчинд exception баригдаж, хоосон settings дамжуулагддаг эсэхийг шалгана.
 */
class SettingsMiddlewareTest extends TestCase
{
    private function createMockRequestWithAttributes(array $attributes): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getAttribute')
            ->willReturnCallback(function (string $name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $request->method('withAttribute')
            ->willReturnCallback(function (string $name, $value) use ($attributes) {
                $newAttrs = $attributes;
                $newAttrs[$name] = $value;
                return $this->createMockRequestWithAttributes($newAttrs);
            });

        return $request;
    }

    private function createHandlerCapturingRequest(?ResponseInterface $response = null): array
    {
        $container = new \stdClass();
        $container->request = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $resp = $response ?? $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use ($container, $resp) {
                $container->request = $req;
                return $resp;
            });

        return [$handler, $container];
    }

    // =============================================
    // Localization байхгүй бол хоосон settings
    // =============================================

    public function testEmptySettingsWhenNoLocalization(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        $request = $this->createMockRequestWithAttributes(['pdo' => null]);
        [$handler, $container] = $this->createHandlerCapturingRequest();

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);

        $settings = $container->request->getAttribute('settings');
        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    // =============================================
    // Localization байгаа ч PDO байхгүй бол хоосон settings
    // =============================================

    public function testEmptySettingsWhenNoPdo(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        $request = $this->createMockRequestWithAttributes([
            'pdo' => null,
            'localization' => ['code' => 'mn'],
        ]);
        [$handler, $container] = $this->createHandlerCapturingRequest();

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);

        $settings = $container->request->getAttribute('settings');
        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    // =============================================
    // Settings attribute бүтэц
    // =============================================

    public function testSettingsAttributeIsAlwaysArray(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        $request = $this->createMockRequestWithAttributes([]);
        [$handler, $container] = $this->createHandlerCapturingRequest();

        $middleware->process($request, $handler);

        $settings = $container->request->getAttribute('settings');
        $this->assertIsArray($settings);
    }

    // =============================================
    // Response буцаах
    // =============================================

    public function testMiddlewareReturnsResponse(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        $request = $this->createMockRequestWithAttributes([]);
        [$handler, ] = $this->createHandlerCapturingRequest();

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    // =============================================
    // Localization code null бол exception баригдана
    // =============================================

    public function testExceptionCaughtWhenLocalizationCodeMissing(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        // localization attribute байгаа ч 'code' key байхгүй
        $request = $this->createMockRequestWithAttributes([
            'pdo' => null,
            'localization' => ['language' => ['en' => []]],
        ]);
        [$handler, $container] = $this->createHandlerCapturingRequest();

        // Exception баригдаж, хоосон settings буцаах ёстой (crash хийхгүй)
        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $settings = $container->request->getAttribute('settings');
        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    // =============================================
    // Handler дуудагдсан эсэх
    // =============================================

    public function testHandlerIsAlwaysCalled(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new SettingsMiddleware();
        $request = $this->createMockRequestWithAttributes([]);

        $handlerCalled = false;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->method('handle')
            ->willReturnCallback(function () use (&$handlerCalled, $response) {
                $handlerCalled = true;
                return $response;
            });

        $middleware->process($request, $handler);

        $this->assertTrue($handlerCalled, 'Handler should always be called even when settings fail');
    }
}
