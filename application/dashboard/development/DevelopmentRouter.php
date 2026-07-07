<?php

namespace Dashboard\Development;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class DevelopmentRouter
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн модулийн маршрутын тохиргоо.
 *
 * @package Dashboard\Development
 */
class DevelopmentRouter extends Router
{
    /**
     * Хөгжүүлэлтийн модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        // DevRequest маршрутууд
        $this->GET('/dev-requests', [DevRequestController::class, 'index'])->name('dev-requests');
        $this->GET('/dev-requests/list', [DevRequestController::class, 'list'])->name('dev-requests-list');
        $this->GET('/dev-requests/create', [DevRequestController::class, 'create'])->name('dev-requests-create');
        $this->POST('/dev-requests/store', [DevRequestController::class, 'store'])->name('dev-requests-store')->middleware([CsrfMiddleware::class]);
        $this->GET('/dev-requests/view/{uint:id}', [DevRequestController::class, 'view'])->name('dev-requests-view');
        $this->POST('/dev-requests/respond', [DevRequestController::class, 'respond'])->name('dev-requests-respond')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/dev-requests/delete', [DevRequestController::class, 'delete'])->name('dev-requests-delete')->middleware([CsrfMiddleware::class]);
    }
}
