<?php

namespace Raptor\Dashboard;

use codesaur\Router\Router;

class MenuRouter extends Router
{
    public function __construct()
    {
        $this->GET('/user/option', [MenuController::class, 'userOption'])->name('user-option');
        $this->GET('/manage/menu', [MenuController::class, 'manage'])->name('manage-menu');
        $this->POST('/manage/menu/insert', [MenuController::class, 'insert'])->name('manage-menu-insert');
        $this->PUT('/manage/menu/update', [MenuController::class, 'update'])->name('manage-menu-update');
        $this->DELETE('/manage/menu/delete', [MenuController::class, 'delete'])->name('manage-menu-delete');
    }
}
