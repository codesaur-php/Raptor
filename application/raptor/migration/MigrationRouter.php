<?php

namespace Raptor\Migration;

use codesaur\Router\Router;

/**
 * Class MigrationRouter
 *
 * Migration dashboard-ийн чиглүүлэгч (Router).
 *
 * Маршрутууд:
 *  - GET  /dashboard/migrations         → Migrations хуудас (index)
 *  - GET  /dashboard/migrations/status  → Төлөв байдал JSON (AJAX)
 *  - GET  /dashboard/migrations/view    → SQL файлын агуулга JSON (AJAX)
 *
 * @package Raptor\Migration
 */
class MigrationRouter extends Router
{
    /**
     * Migration модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        $this->GET('/dashboard/migrations', [MigrationController::class, 'index'])
            ->name('migrations');

        $this->GET('/dashboard/migrations/status', [MigrationController::class, 'status'])
            ->name('migrations-status');

        $this->GET('/dashboard/migrations/view', [MigrationController::class, 'view'])
            ->name('migrations-view');
    }
}
