<?php

namespace Raptor\Localization;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class LocalizationMiddleware
 *
 * Нутагшуулалтын (localization) middleware.
 * Dashboard болон Web app хоёуланд ашиглагдана.
 *
 * Ажиллагааны дараалал:
 *  1) LanguageModel-ээс идэвхтэй хэлүүдийг ачаална
 *  2) Session-д хадгалсан хэлийг шалгаж, олдохгүй бол default хэлийг сонгоно
 *  3) TextModel-ээс тухайн хэл дээрх орчуулгуудыг ачаална
 *  4) Request attribute 'localization' болгон дамжуулна:
 *     [
 *         'language'    => [...],
 *         'code'        => 'mn',
 *         'text'        => ['keyword' => 'translated text', ...],
 *         'session_key' => 'RAPTOR_LANGUAGE_CODE'
 *     ]
 *
 * Session key нь app бүрт тусдаа байх боломжтой:
 *  - Dashboard: new LocalizationMiddleware('RAPTOR_LANGUAGE_CODE')
 *  - Web:       new LocalizationMiddleware('WEB_LANGUAGE_CODE')
 */
class LocalizationMiddleware implements MiddlewareInterface
{
    private string $sessionKey;

    /**
     * @param string $sessionKey Session-д хэлний кодыг хадгалах key
     */
    public function __construct(string $sessionKey = 'RAPTOR_LANGUAGE_CODE')
    {
        $this->sessionKey = $sessionKey;
    }

    /**
     * Хэлний жагсаалтыг DB-ээс татах.
     * Алдаа гарвал fallback: English.
     */
    private function retrieveLanguage(ServerRequestInterface $request): array
    {
        try {
            $model = new LanguageModel($request->getAttribute('pdo'));
            $rows = $model->retrieve();
            if (empty($rows)) {
                throw new \Exception('Languages not found!');
            }
            return $rows;
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            return ['en' => ['locale' => 'en-US', 'title' => 'English']];
        }
    }

    /**
     * Сонгогдсон хэл дээрх орчуулгын текстүүдийг ачаална.
     */
    private function retrieveTexts(ServerRequestInterface $request, string $langCode): array
    {
        try {
            $model = new TextModel($request->getAttribute('pdo'));
            return $model->retrieve($langCode);
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            return [];
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);

        if (isset($_SESSION[$this->sessionKey])
            && isset($language[$_SESSION[$this->sessionKey]])
        ) {
            $code = $_SESSION[$this->sessionKey];
        } else {
            $code = \key($language);
        }

        $text = $this->retrieveTexts($request, $code);

        return $handler->handle(
            $request->withAttribute('localization', [
                'language'    => $language,
                'code'        => $code,
                'text'        => $text,
                'session_key' => $this->sessionKey
            ])
        );
    }
}
