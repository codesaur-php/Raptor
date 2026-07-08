<?php

namespace Dashboard\Template;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Class TemplateRouter
 *
 * Гүйцэтгэх үндсэн үүргүүд:
 *   - Dashboard менюг удирдах (үүсгэх, засах, устгах)
 *
 * Энэ Router нь TemplateController-т холбогддог.
 *
 * @package Dashboard\Template
 */
class TemplateRouter extends Router
{
    /**
     * TemplateRouter constructor.
     *
     * Энд Dashboard UI-тай холбоотой бүх замуудыг бүртгэнэ.
     *
     * Жишээ:
     *   GET    /dashboard/manage/menu             -> Меню удирдлага хуудас
     *   POST   /dashboard/manage/menu/insert      -> Шинэ меню үүсгэх
     *   PUT    /dashboard/manage/menu/update      -> Одоогийн менюг шинэчлэх
     *   DELETE /dashboard/manage/menu/delete      -> Менюг устгах
     */
    public function __construct()
    {
        /**
         * МЕНЮ УДИРДЛАГА (Dashboard -> System -> Menu Management)
         */

        $this->GET(
            '/manage/menu',
            [TemplateController::class, 'manageMenu']
        );

        $this->POST(
            '/manage/menu/insert',
            [TemplateController::class, 'manageMenuInsert']
        )->name('manage-menu-insert')->middleware([CsrfMiddleware::class]);

        $this->PUT(
            '/manage/menu/update',
            [TemplateController::class, 'manageMenuUpdate']
        )->name('manage-menu-update')->middleware([CsrfMiddleware::class]);

        $this->DELETE(
            '/manage/menu/delete',
            [TemplateController::class, 'manageMenuDelete']
        )->name('manage-menu-delete')->middleware([CsrfMiddleware::class]);
    }
}
