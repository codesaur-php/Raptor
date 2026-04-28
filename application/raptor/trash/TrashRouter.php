<?php

namespace Raptor\Trash;

use codesaur\Router\Router;

/**
 * Class TrashRouter
 *
 * Trash модулийн маршрутууд.
 *
 * @package Raptor\Trash
 */
class TrashRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/trash', [TrashController::class, 'index'])->name('trash');
        $this->GET('/dashboard/trash/list', [TrashController::class, 'list'])->name('trash-list');
        $this->GET('/dashboard/trash/view/{uint:id}', [TrashController::class, 'view'])->name('trash-view');
        $this->POST('/dashboard/trash/restore/{uint:id}', [TrashController::class, 'restore'])->name('trash-restore');
        $this->DELETE('/dashboard/trash/delete', [TrashController::class, 'delete'])->name('trash-delete');
        $this->DELETE('/dashboard/trash/empty', [TrashController::class, 'empty'])->name('trash-empty');
    }
}
