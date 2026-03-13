<?php

namespace Dashboard\Home;

use Psr\Log\LogLevel;

/**
 * Class HomeController
 * ------------------------------------------------------------------
 * Dashboard-ийн нүүр хуудасны контроллер.
 *
 * @package Dashboard\Home
 */
class HomeController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Dashboard-ийн нүүр хуудсыг харуулах.
     */
    public function index()
    {
        $this->twigDashboard(
            __DIR__ . '/home.html',
            ['web_log_stats' => $this->twigTemplate(__DIR__ . '/web-log-stats.html')]
        )->render();

        $this->log('dashboard', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
}
