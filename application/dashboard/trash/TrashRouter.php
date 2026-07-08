<?php

namespace Dashboard\Trash;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Class TrashRouter
 *
 * Trash модулийн маршрутууд.
 *
 * @package Dashboard\Trash
 */
class TrashRouter extends Router
{
    public function __construct()
    {
        $this->GET('/trash', [TrashController::class, 'index'])->name('trash');
        $this->GET('/trash/list', [TrashController::class, 'list'])->name('trash-list');
        $this->GET('/trash/view/{uint:id}', [TrashController::class, 'view'])->name('trash-view');
        $this->POST('/trash/restore/{uint:id}', [TrashController::class, 'restore'])->name('trash-restore')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/trash/delete', [TrashController::class, 'delete'])->name('trash-delete')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/trash/empty', [TrashController::class, 'empty'])->name('trash-empty')->middleware([CsrfMiddleware::class]);
    }
}
