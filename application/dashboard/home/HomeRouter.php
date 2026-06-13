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
    /**
     * Dashboard Home модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        $this->GET('/', [HomeController::class, 'index'])->name('home');
        
        $this->GET('/search', [SearchController::class, 'search'])->name('dashboard-search');

        $this->GET('/stats', [WebLogStatsController::class, 'stats'])->name('dashboard-stats');
        $this->GET('/log-stats', [WebLogStatsController::class, 'logStats'])->name('dashboard-log-stats');
    }
}
