<?php

namespace Raptor\Development;

use codesaur\Router\Router;

/**
 * Class DevelopmentRouter
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн модулийн маршрутын тохиргоо.
 *
 * DevRequest, SqlTerminal, ErrorLog гэсэн 3 feature-ийн
 * бүх маршрутыг нэгтгэсэн.
 *
 * @package Raptor\Development
 */
class DevelopmentRouter extends Router
{
    public function __construct()
    {
        // DevRequest маршрутууд
        $this->GET('/dashboard/dev-requests', [DevRequestController::class, 'index'])->name('dev-requests');
        $this->GET('/dashboard/dev-requests/list', [DevRequestController::class, 'list'])->name('dev-requests-list');
        $this->GET('/dashboard/dev-requests/create', [DevRequestController::class, 'create'])->name('dev-requests-create');
        $this->POST('/dashboard/dev-requests/store', [DevRequestController::class, 'store'])->name('dev-requests-store');
        $this->GET('/dashboard/dev-requests/view/{uint:id}', [DevRequestController::class, 'view'])->name('dev-requests-view');
        $this->POST('/dashboard/dev-requests/respond', [DevRequestController::class, 'respond'])->name('dev-requests-respond');
        $this->DELETE('/dashboard/dev-requests/deactivate', [DevRequestController::class, 'deactivate'])->name('dev-requests-deactivate');

        // SqlTerminal маршрутууд
        $this->GET('/dashboard/sql-terminal', [SqlTerminalController::class, 'index'])->name('sql-terminal');
        $this->POST('/dashboard/sql-terminal/execute', [SqlTerminalController::class, 'execute'])->name('sql-terminal-execute');

        // ErrorLog маршрутууд
        $this->GET('/dashboard/error-log', [FileManagerController::class, 'errorLogIndex'])->name('error-log');
        $this->GET('/dashboard/error-log/read', [FileManagerController::class, 'errorLogRead'])->name('error-log-read');

        // FileManager маршрутууд
        $this->GET('/dashboard/file-manager', [FileManagerController::class, 'index'])->name('file-manager');
        $this->GET('/dashboard/file-manager/browse', [FileManagerController::class, 'browse'])->name('file-manager-browse');
        $this->GET('/dashboard/file-manager/read', [FileManagerController::class, 'readFile'])->name('file-manager-read');
        $this->GET('/dashboard/file-manager/download', [FileManagerController::class, 'download'])->name('file-manager-download');
    }
}
