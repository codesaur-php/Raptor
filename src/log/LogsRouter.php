<?php

namespace Raptor\Log;

use codesaur\Router\Router;

class LogsRouter extends Router
{
    function __construct()
    {
        $this->GET('/logs', [AdvancedLogController::class, 'index'])->name('logs');
        $this->GET('/logs/view', [AdvancedLogController::class, 'view'])->name('logs-view');
    }
}
