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
class HomeController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /**
     * Dashboard-ийн нүүр хуудсыг харуулах.
     */
    public function index()
    {
        // Зочны IP / User-Agent агуулдаг тул статистикийг зөвхөн system_logger эрхтэй хэрэглэгчид харуулна.
        // PHP хувилбар, ini тохиргоо, php.ini-ийн зам нь серверийн мэдээлэл тул зөвхөн system_coder-т.
        $this->dashboardTemplate(
            __DIR__ . '/home.html',
            [
                'web_log_stats' => $this->isUserCan('system_logger')
                    ? $this->template(__DIR__ . '/web-log-stats.html')
                    : '',
                'php_status' => $this->isUser('system_coder') ? PhpSecurityStatus::check() : null
            ]
        )->render();

        $this->log('dashboard', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
}
