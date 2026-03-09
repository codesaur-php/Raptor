<?php

namespace Dashboard\Home;

use codesaur\Router\Router;

/**
 * Class HomeRouter
 * ----------------------------------------------------------------------
 * Dashboard -> Home module-ийн HTTP маршрут тодорхойлогч класс.
 *
 * @package Dashboard\Home
 */
class HomeRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard', [HomeController::class, 'index'])->name('home');
        $this->GET('/dashboard/stats', [HomeController::class, 'stats'])->name('dashboard-stats');
        $this->GET('/dashboard/log-stats', [HomeController::class, 'logStats'])->name('dashboard-log-stats');
    }
}
