<?php

namespace Raptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        $this->GET_POST('/settings', [SettingsController::class, 'index'])->name('settings');
        $this->POST('/settings/files', [SettingsController::class, 'files'])->name('settings-files');
        $this->POST('/settings/mailer', [SettingsController::class, 'mailer'])->name('settings-mailer');
    }
}
