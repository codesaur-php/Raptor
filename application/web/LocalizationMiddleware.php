<?php

namespace Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Raptor\Localization\LanguageModel;
use Raptor\Localization\TextModel;

/**
 * Class LocalizationMiddleware
 * ------------------------------------------------------------------
 * Web давхаргын Localization Middleware
 *
 * Энэ middleware нь веб сайтын UI текстүүдийг хэл дээр нь нутагшуулж,
 * Request -> Middleware -> Controller -> Template давхаргуудад дамжуулах зориулалттай.
 *
 * ------------------------------------------------------------------
 * Middleware-ийн ажиллагааны дараалал
 * ------------------------------------------------------------------
 * 1) `LanguageModel` ашиглан боломжит хэлүүдийг DB-ээс татна  
 *    - Хэрэв алдаа гарвал -> fallback: English (en-US)
 *
 * 2) Session (`WEB_LANGUAGE_CODE`) дээр хэрэглэгч сонгосон хэл байвал
 *    тэрийг хэрэглэнэ, эс бөгөөс эхний хэл кодыг авна.
 *
 * 3) Сонгогдсон хэл дээрх орчуулгын текстүүдийг ачаална
 *
 * 4) Request объектод attributes хэлбэрээр дамжуулах:
 *
 *      $request->getAttribute('localization') =
 *      [
 *          'language' => [...],  // боломжит хэлүүд
 *          'code'     => 'mn',   // сонгосон хэл код
 *          'text'     => [...]   // орчуулгын key/value массив
 *      ]
 *
 * ------------------------------------------------------------------
 * @package Web
 */
class LocalizationMiddleware implements MiddlewareInterface
{   
    /**
     * Нийт боломжит хэлүүдийг DB-ээс татах
     * (алдаа гарвал English рүү fallback хийнэ)
     */
    private function retrieveLanguage(ServerRequestInterface $request)
    {
        try {
            $model = new LanguageModel($request->getAttribute('pdo'));
            $rows = $model->retrieve();
            if (empty($rows)) {
                throw new \Exception('Languages not found!');
            }
            return $rows;
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
            return ['en' => ['locale' => 'en-US', 'title' => 'English']];
        }
    }
    
    /**
     * Сонгогдсон хэл дээр орчуулгын текстүүдийг
     * localization_text хүснэгтээс ачаална.
     */
    private function retrieveTexts(ServerRequestInterface $request, string $langCode)
    {
        try {
            $model = new TextModel($request->getAttribute('pdo'));
            return $model->retrieve($langCode);
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Localization attribute-г Request объектод дамжуулж,
     * дараагийн Middleware/Controller-т өгнө.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);

        if (isset($_SESSION['WEB_LANGUAGE_CODE'])
            && isset($language[$_SESSION['WEB_LANGUAGE_CODE']])
        ) {
            $code = $_SESSION['WEB_LANGUAGE_CODE'];
        } else {
            $code = \key($language);
        }
        
        $text = $this->retrieveTexts($request, $code);
        
        return $handler->handle(
            $request->withAttribute('localization', [
                'language' => $language,
                'code' => $code,
                'text' => $text
            ])
        );
    }
}
