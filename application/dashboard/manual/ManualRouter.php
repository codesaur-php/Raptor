<?php

namespace Dashboard\Manual;

use codesaur\Router\Router;

/**
 * Class ManualRouter
 *
 * Dashboard-ийн гарын авлага модулийн маршрут тодорхойлогч.
 *
 * @package Dashboard\Manual
 */
class ManualRouter extends Router
{
    /**
     * Manual модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        $this->GET('/dashboard/manual', [ManualController::class, 'index'])->name('manual');
        $this->GET('/dashboard/manual/{file}', [ManualController::class, 'view'])->name('manual-view');
    }
}
