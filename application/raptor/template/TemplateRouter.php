<?php

namespace Raptor\Template;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class TemplateRouter
 *
 * Raptor Framework-ийн Template модулийн Dashboard-той холбоотой
 * маршрут (route)-уудыг нэг дор бүртгэн удирддаг Router.
 *
 * Гүйцэтгэх үндсэн үүргүүд:
 *   - Хэрэглэгчийн UI тохиргоонуудыг (хэл, харагдах байдал гэх мэт) авах
 *   - Dashboard менюг удирдах (үүсгэх, засах, устгах)
 *
 * Энэ Router нь TemplateController-т холбогддог.
 *
 * @package Raptor\Template
 */
class TemplateRouter extends Router
{
    /**
     * TemplateRouter constructor.
     *
     * Энд Dashboard UI-тай холбоотой бүх замуудыг бүртгэнэ.
     * 
     * Жишээ:
     *   GET    /dashboard/user/option             -> Хэрэглэгчийн DASHBOARD UI тохиргоо
     *   GET    /dashboard/manage/menu             -> Меню удирдлага хуудас
     *   POST   /dashboard/manage/menu/insert      -> Шинэ меню үүсгэх
     *   PUT    /dashboard/manage/menu/update      -> Одоогийн менюг шинэчлэх
     *   DELETE /dashboard/manage/menu/delete      -> Менюг устгах
     */
    public function __construct()
    {
        /**
         * ХЭРЭГЛЭГЧИЙН DASHBOARD UI ТОХИРГОО
         */
        $this->GET(
            '/user/option',
            [TemplateController::class, 'userOption']
        )->name('user-option');

        /**
         * МЕНЮ УДИРДЛАГА (Dashboard -> System -> Menu Management)
         */

        // Меню жагсаалт, удирдлагын хуудас
        $this->GET(
            '/manage/menu',
            [TemplateController::class, 'manageMenu']
        );

        // Шинэ меню үүсгэх
        $this->POST(
            '/manage/menu/insert',
            [TemplateController::class, 'manageMenuInsert']
        )->name('manage-menu-insert')->middleware([CsrfMiddleware::class]);

        // Меню шинэчлэх
        $this->PUT(
            '/manage/menu/update',
            [TemplateController::class, 'manageMenuUpdate']
        )->name('manage-menu-update')->middleware([CsrfMiddleware::class]);

        // Меню устгах
        $this->DELETE(
            '/manage/menu/delete',
            [TemplateController::class, 'manageMenuDelete']
        )->name('manage-menu-delete')->middleware([CsrfMiddleware::class]);
    }
}
