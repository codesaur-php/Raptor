<?php

namespace Raptor\Log;

use codesaur\Router\Router;

class LogsRouter extends Router
{
    public function __construct()
    {
        $this->GET('/logs', [LogsController::class, 'index'])->name('logs');
        $this->GET('/logs/view', [LogsController::class, 'view'])->name('logs-view');
        $this->GET('/logs/mailer', [LogsController::class, 'mailer'])->name('mailer-logs');
    }
}
