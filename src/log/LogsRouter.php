<?php

namespace Raptor\Log;

use codesaur\Router\Router;

class LogsRouter extends Router
{
    function __construct()
    {
        $this->GET('/logs', [LogsController::class, 'index'])->name('logs');
        $this->GET('/logs/view', [LogsController::class, 'view'])->name('logs-view');
    }
}
