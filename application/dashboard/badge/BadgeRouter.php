<?php

namespace Dashboard\Badge;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Class BadgeRouter
 * ---------------------------------------------------------------
 * Dashboard Badge System - Маршрутын бүртгэл
 *
 * Dashboard sidebar badge системийн 2 маршрутыг бүртгэнэ.
 * Dashboard-ийн JavaScript sidebar ачаалагдах бүрт badge тоог
 * авахаар GET хүсэлт илгээж, admin module-ийн линк дарах бүрт
 * POST хүсэлтээр seen болгоно.
 *
 * Маршрутын path-ууд mount-naive ('/badges') - Dashboard\Application-д
 * бүртгэгдэж entry point-ийн ->mount('/dashboard') тохиргоогоор
 * бүтэн '/dashboard/badges' хаяг болно.
 *
 * Org-scoped badge хэрэгтэй бол энэ router-т өөрчлөлт хэрэггүй:
 * BadgeController::orgScopedModules()-ийн body-г шууд засварлахад л
 * хангалттай ({@see BadgeController::orgScopedModules()} док-ийг үзнэ үү).
 *
 * @package Dashboard\Badge
 */
class BadgeRouter extends Router
{
    public function __construct()
    {
        // Бүх module-ийн badge тоог JSON-аар буцаана.
        // Dashboard sidebar ачаалагдах бүрт JS-ээс (initSidebarBadges) дуудагдана.
        // Named route: dashboard.html layout дотор initSidebarBadges()-ийн
        // аргумент болж {{ 'dashboard-badges'|link }} хэлбэрээр хэвлэгддэг
        // тул нэр заавал хэрэгтэй.
        // GET тул CsrfMiddleware хэрэггүй (GET/HEAD/OPTIONS шалгалтгүй нэвтэрдэг),
        // эрхийн шалгалт controller дотроо хийгдэнэ (isUserAuthorized + RBAC).
        $this->GET(
            '/badges',
            [BadgeController::class, 'list']
        )->name('dashboard-badges');

        // Тухайн module-г seen болгож badge-г цэвэрлэнэ.
        // Admin sidebar дээр module линк дарахад JS-ээс csrfFetch()-ээр дуудагдана.
        // Body: { module: "/dashboard/news" } - бүтэн (mounted) module key.
        // Mutating POST тул CsrfMiddleware заавал (per-route CSRF дүрэм).
        $this->POST(
            '/badges/seen',
            [BadgeController::class, 'seen']
        )->middleware([CsrfMiddleware::class]);
    }
}
