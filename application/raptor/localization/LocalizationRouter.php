<?php 

namespace Raptor\Localization;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class LocalizationRouter
 * 
 * Raptor framework-ийн Локализацийн модульд зориулсан маршрут 
 * тодорхойлогч класс юм. 
 * 
 * Энэ нь codesaur/router компонентын Router классыг өргөтгөн,
 * хэлний тохиргоо болон текстийн орчуулгын CRUD үйлдлүүдэд шаардлагатай
 * бүх HTTP маршрутыг бүртгэнэ.
 * 
 * Маршрутууд нь dashboard хэсэгт байрлах:
 *  - LanguageController - системийн хэлүүдийг удирдах
 *  - TextController - орчуулгын текстүүдийг удирдах
 *  - LocalizationController - локализацийн ерөнхий хуудас
 * 
 * @package Raptor\Localization
 */
class LocalizationRouter extends Router
{
    /**
     * LocalizationRouter constructor.
     *
     * Энд локализацийн бүх CRUD маршрут бүртгэгдэнэ.
     * GET, POST, PUT, DELETE зэрэг HTTP дүрэм тус бүрт тохирох
     * controller action-ууд холбогдоно.
     */
    public function __construct()
    {
        /**
         * Локализацийн үндсэн Dashboard хуудас.
         * Example: GET /dashboard/localization
         */
        $this->GET('/localization', [LocalizationController::class, 'index'])->name('localization');
        
        /**
         * Хэл нэмэх (GET + POST нийлсэн).
         * Example: GET/POST /dashboard/language
         */
        $this->GET_POST('/language', [LanguageController::class, 'insert'])->name('language-insert')->middleware([CsrfMiddleware::class]);

        /**
         * Нэг хэлний мэдээллийг харах.
         * Example: GET /dashboard/language/view/3
         */
        $this->GET('/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');

        /**
         * Хэл шинэчлэх PUT хүсэлт.
         * Example: GET/PUT /dashboard/language/3
         */
        $this->GET_PUT('/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update')->middleware([CsrfMiddleware::class]);

        /**
         * Хэл устгах (hard delete).
         * Example: DELETE /dashboard/language/delete
         */
        $this->DELETE('/language/delete', [LanguageController::class, 'delete'])->name('delete-language')->middleware([CsrfMiddleware::class]);
        
        /**
         * Орчуулгын текст шинээр нэмэх.
         * Example: GET/POST /dashboard/text
         */
        $this->GET_POST('/text', [TextController::class, 'insert'])->name('text-insert')->middleware([CsrfMiddleware::class]);

        /**
         * Орчуулгын текст шинэчлэх.
         * Example: GET/PUT /dashboard/text/12
         */
        $this->GET_PUT('/text/{uint:id}', [TextController::class, 'update'])->name('text-update')->middleware([CsrfMiddleware::class]);

        /**
         * Орчуулгын текстийн дэлгэрэнгүй харах.
         * Example: GET /dashboard/text/view/12
         */
        $this->GET('/text/view/{uint:id}', [TextController::class, 'view'])->name('text-view');

        /**
         * Орчуулгын текст устгах.
         * Example: DELETE /dashboard/text/delete
         */
        $this->DELETE('/text/delete', [TextController::class, 'delete'])->name('text-delete')->middleware([CsrfMiddleware::class]);
    }
}
