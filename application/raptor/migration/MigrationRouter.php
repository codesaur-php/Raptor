W<?php

namespace Raptor\Migration;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class MigrationRouter
 *
 * File-based migration UI-ийн route-ууд.
 *
 *   GET  /dashboard/migrations         -> Migration index page
 *   GET  /dashboard/migrations/status  -> JSON: folder бүрийн pending/ran жагсаалт
 *   GET  /dashboard/migrations/view    -> SQL контент modal
 *   POST /dashboard/migrations/upload  -> .sql файл хүлээж авах
 *   POST /dashboard/migrations/apply   -> pending файлыг ажиллуулах
 *   POST /dashboard/migrations/delete  -> pending файлыг устгах
 *
 * @package Raptor\Migration
 */
class MigrationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/migrations', [MigrationController::class, 'index'])
            ->name('migrations');

        $this->GET('/migrations/status', [MigrationController::class, 'status'])
            ->name('migrations-status');

        $this->GET('/migrations/view', [MigrationController::class, 'view'])
            ->name('migrations-view');

        $this->POST('/migrations/upload', [MigrationController::class, 'upload'])
            ->name('migrations-upload')->middleware([CsrfMiddleware::class]);

        $this->POST('/migrations/apply', [MigrationController::class, 'apply'])
            ->name('migrations-apply')->middleware([CsrfMiddleware::class]);

        $this->POST('/migrations/delete', [MigrationController::class, 'delete'])
            ->name('migrations-delete')->middleware([CsrfMiddleware::class]);
    }
}
