<?php

namespace Raptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        $this->GET('/settings', [SettingsController::class, 'index'])->name('settings');
    }
}
