<?php

namespace Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Raptor\Localization\LocalizationMiddleware;

/**
 * LocalizationMiddleware - хэл тодорхойлох, session key, fallback логикийг тестлэх.
 *
 * DB-ээс хамааралтай retrieveLanguage/retrieveTexts нь DB-гүйгээр
 * fallback руу орох тул энд тухайн fallback зан байдлыг шалгана.
 */
class LocalizationMiddlewareTest extends TestCase
{
    private function createMockRequest(array $attributes = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getAttribute')
            ->willReturnCallback(function (string $name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        // withAttribute дуудагдах бүрт шинэ request mock буцаах
        $request->method('withAttribute')
            ->willReturnCallback(function (string $name, $value) use ($attributes) {
                $newAttrs = $attributes;
                $newAttrs[$name] = $value;
                return $this->createMockRequestWithAttributes($newAttrs);
            });

        return $request;
    }

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

    // =============================================
    // Constructor - session key тохируулах
    // =============================================

    public function testDefaultSessionKey(): void
    {
        $middleware = new LocalizationMiddleware();

        $ref = new \ReflectionProperty(LocalizationMiddleware::class, 'sessionKey');
        $ref->setAccessible(true);

        $this->assertSame('RAPTOR_LANGUAGE_CODE', $ref->getValue($middleware));
    }

    public function testCustomSessionKey(): void
    {
        $middleware = new LocalizationMiddleware('WEB_LANGUAGE_CODE');

        $ref = new \ReflectionProperty(LocalizationMiddleware::class, 'sessionKey');
        $ref->setAccessible(true);

        $this->assertSame('WEB_LANGUAGE_CODE', $ref->getValue($middleware));
    }

    // =============================================
    // Fallback behavior - PDO байхгүй бол English fallback
    // =============================================

    public function testFallbackToEnglishWhenNoPdo(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new LocalizationMiddleware();
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertNotNull($capturedRequest);

        $localization = $capturedRequest->getAttribute('localization');
        $this->assertIsArray($localization);
        $this->assertArrayHasKey('language', $localization);
        $this->assertArrayHasKey('code', $localization);
        $this->assertArrayHasKey('text', $localization);
        $this->assertArrayHasKey('session_key', $localization);
    }

    public function testFallbackLanguageIsEnglish(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new LocalizationMiddleware();
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $middleware->process($request, $handler);

        $localization = $capturedRequest->getAttribute('localization');

        // Fallback-д зөвхөн English байна
        $this->assertSame('en', $localization['code']);
        $this->assertArrayHasKey('en', $localization['language']);
        $this->assertSame('English', $localization['language']['en']['title']);
    }

    public function testFallbackTextsAreEmptyArray(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new LocalizationMiddleware();
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $middleware->process($request, $handler);

        $localization = $capturedRequest->getAttribute('localization');

        // Texts нь хоосон array байх ёстой (DB-гүй тул)
        $this->assertSame([], $localization['text']);
    }

    // =============================================
    // Session key дамжуулалт
    // =============================================

    public function testSessionKeyIsPassedInLocalization(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $sessionKey = 'MY_CUSTOM_LANG_KEY';
        $middleware = new LocalizationMiddleware($sessionKey);
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $middleware->process($request, $handler);

        $localization = $capturedRequest->getAttribute('localization');
        $this->assertSame($sessionKey, $localization['session_key']);
    }

    // =============================================
    // Session language resolution
    // =============================================

    public function testSessionLanguageIgnoredWhenNotInLanguageList(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        // Session-д 'fr' хэл байгаа ч fallback language list-д зөвхөн 'en' байна
        $_SESSION['RAPTOR_LANGUAGE_CODE'] = 'fr';

        $middleware = new LocalizationMiddleware();
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $middleware->process($request, $handler);

        $localization = $capturedRequest->getAttribute('localization');

        // 'fr' нь language list-д байхгүй тул default 'en' сонгогдох ёстой
        $this->assertSame('en', $localization['code']);

        unset($_SESSION['RAPTOR_LANGUAGE_CODE']);
    }

    public function testLocalizationAttributeStructure(): void
    {
        if (!defined('CODESAUR_DEVELOPMENT')) {
            define('CODESAUR_DEVELOPMENT', false);
        }

        $middleware = new LocalizationMiddleware('TEST_KEY');
        $request = $this->createMockRequest(['pdo' => null]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedRequest, $response) {
                $capturedRequest = $req;
                return $response;
            });

        $middleware->process($request, $handler);

        $localization = $capturedRequest->getAttribute('localization');

        // Бүтэц бүрэн байх ёстой
        $this->assertCount(4, $localization);
        $this->assertArrayHasKey('language', $localization);
        $this->assertArrayHasKey('code', $localization);
        $this->assertArrayHasKey('text', $localization);
        $this->assertArrayHasKey('session_key', $localization);
    }
}
