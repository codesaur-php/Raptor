<?php

namespace Dashboard\Localization;

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
 *  2) Хэлийг дараах давуу эрхээр сонгоно:
 *     a) 'language_prefix' request attribute (index.php URL-ийн /xx/ prefix-ээс
 *        тавьдаг) - идэвхтэй хэл бол сонгоно, биш бол 404 шиднэ
 *     b) Session-д хадгалсан хэл (session key өгсөн үед л)
 *     c) Default хэл (LanguageModel::retrieve()-ийн эхний хэл)
 *  3) TextModel-ээс тухайн хэл дээрх орчуулгуудыг ачаална
 *  4) Request attribute 'localization' болгон дамжуулна:
 *     [
 *         'language'    => [...],
 *         'code'        => 'mn',
 *         'text'        => ['keyword' => 'translated text', ...],
 *         'session_key' => 'RAPTOR_LANGUAGE_CODE'   // эсвэл null
 *     ]
 *
 * Session key нь app бүрт тусдаа:
 *  - Dashboard: new LocalizationMiddleware('RAPTOR_LANGUAGE_CODE') - хэл session-д
 *  - Web:       new LocalizationMiddleware(null) - хэл зөвхөн URL prefix-ээс,
 *               session огт ашиглахгүй (нэг URL = нэг хэл, хайлтын систем
 *               индексжүүлэх боломжтой)
 */
class LocalizationMiddleware implements MiddlewareInterface
{
    private ?string $sessionKey;

    /**
     * @param string|null $sessionKey Session-д хэлний кодыг хадгалах key.
     *                                null бол session ашиглахгүй (URL prefix эсвэл default)
     */
    public function __construct(?string $sessionKey = 'RAPTOR_LANGUAGE_CODE')
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
            $cache = $request->getAttribute('container')?->get('cache');
            $cached = $cache?->get('languages');
            if ($cached !== null) {
                return $cached;
            }

            $model = new LanguageModel($request->getAttribute('pdo'));
            $rows = $model->retrieve();
            if (empty($rows)) {
                throw new \Exception('Languages not found!');
            }

            $cache?->set('languages', $rows);
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
            $cache = $request->getAttribute('container')?->get('cache');
            $cached = $cache?->get("texts.$langCode");
            if ($cached !== null) {
                return $cached;
            }

            $model = new TextModel($request->getAttribute('pdo'));
            $texts = $model->retrieve($langCode);

            $cache?->set("texts.$langCode", $texts);
            return $texts;
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

        $prefix = $request->getAttribute('language_prefix');
        if ($prefix !== null) {
            // URL prefix нь идэвхтэй хэл биш (/de/... гэх мэт) - хуудас байхгүй
            if (!isset($language[$prefix])) {
                throw new \Error("Unknown language prefix [$prefix]", 404);
            }
            $code = $prefix;
        } elseif ($this->sessionKey !== null
            && isset($_SESSION[$this->sessionKey])
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
