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
        // '/' (dashboard root) нь нэргүй хэвээр - public вэб дэх '{{ index }}/dashboard'
        // CTA/footer линк болон шууд хандалт 404 болохоос сэргийлнэ.
        $this->GET('/', [HomeController::class, 'index']);

        // '/home' нь нэрлэгдсэн home route - 'home'|link үүнийг заана.
        // dashboard.html дээрх sidebar-ийн active-detection нь href.startsWith(link)-ээр
        // ажилладаг тул home link '/' байвал бүх дэд хуудсанд агуулагдаж байнга active болно.
        // Тусдаа '/home' зам ашигласнаар зөвхөн home хуудсанд орсон үед л active болно.
        $this->GET('/home', [HomeController::class, 'index'])->name('home');
        
        $this->GET('/search', [SearchController::class, 'search'])->name('dashboard-search');

        $this->GET('/stats', [WebLogStatsController::class, 'stats'])->name('dashboard-stats');
        $this->GET('/log-stats', [WebLogStatsController::class, 'logStats'])->name('dashboard-log-stats');
    }
}
