<?php

namespace Raptor\Development;

use codesaur\Router\Router;

/**
 * Class DevelopmentRouter
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн модулийн маршрутын тохиргоо.
 *
 * @package Raptor\Development
 */
class DevelopmentRouter extends Router
{
    /**
     * Хөгжүүлэлтийн модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        // DevRequest маршрутууд
        $this->GET('/dashboard/dev-requests', [DevRequestController::class, 'index'])->name('dev-requests');
        $this->GET('/dashboard/dev-requests/list', [DevRequestController::class, 'list'])->name('dev-requests-list');
        $this->GET('/dashboard/dev-requests/create', [DevRequestController::class, 'create'])->name('dev-requests-create');
        $this->POST('/dashboard/dev-requests/store', [DevRequestController::class, 'store'])->name('dev-requests-store');
        $this->GET('/dashboard/dev-requests/view/{uint:id}', [DevRequestController::class, 'view'])->name('dev-requests-view');
        $this->POST('/dashboard/dev-requests/respond', [DevRequestController::class, 'respond'])->name('dev-requests-respond');
        $this->DELETE('/dashboard/dev-requests/delete', [DevRequestController::class, 'delete'])->name('dev-requests-delete');
    }
}
