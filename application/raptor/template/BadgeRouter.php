<?php

namespace Raptor\Template;

use codesaur\Router\Router;

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
 * @package Raptor\Template
 */
class BadgeRouter extends Router
{
    public function __construct()
    {
        // Бүх module-ийн badge тоог JSON-аар буцаана.
        // Dashboard sidebar ачаалагдах бүрт JS-ээс дуудагдана.
        // Named route: template дотор {{ 'dashboard-badges'|link }} ашиглана.
        $this->GET(
            '/dashboard/badges',
            [BadgeController::class, 'list']
        )->name('dashboard-badges');

        // Тухайн module-г seen болгож badge-г цэвэрлэнэ.
        // Admin sidebar дээр module линк дарахад JS-ээс дуудагдана.
        // Body: { module: "/dashboard/news" }
        $this->POST(
            '/dashboard/badges/seen',
            [BadgeController::class, 'seen']
        );
    }
}
