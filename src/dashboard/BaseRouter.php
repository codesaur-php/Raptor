<?php

namespace Raptor\Dashboard;

use codesaur\Router\Router;

class BaseRouter extends Router
{
    function __construct()
    {
        $this->GET('/', [DashboardController::class, 'index'])->name('home');
    }
}
