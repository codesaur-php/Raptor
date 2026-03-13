<?php

namespace Dashboard\Home;

/**
 * Class WebLogStatsController
 * ------------------------------------------------------------------
 * Вэб сайтын зочлолын болон системийн лог статистикийн JSON API контроллер.
 *
 * @package Dashboard\Home
 */
class WebLogStatsController extends \Raptor\Controller
{
    /**
     * Вэб сайтын зочлолын статистик мэдээллийг JSON-оор буцаах.
     */
    public function stats()
    {
        try {
            $stats = new WebLogStats($this->pdo);
            $this->respondJSON($stats->getStats());
        } catch (\Throwable $err) {
            $code = (int)$err->getCode();
            $this->respondJSON(['status' => 'error', 'message' => $err->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    /**
     * Системийн бусад *_log хүснэгтүүдийн статистикийг JSON-оор буцаах.
     */
    public function logStats()
    {
        try {
            $stats = new WebLogStats($this->pdo);
            $this->respondJSON(['status' => 'success', 'logs' => $stats->getLogStats()]);
        } catch (\Throwable $err) {
            $code = (int)$err->getCode();
            $this->respondJSON(['status' => 'error', 'message' => $err->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }
}
