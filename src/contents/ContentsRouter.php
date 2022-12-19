<?php

namespace Raptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        $this->GET('/references', [ReferencesController::class, 'index'])->name('references');
        $this->GET('/reference/datatable/{table}', [ReferencesController::class, 'datatable'])->name('reference-datatable');
        $this->GET_POST('/reference/{table}', [ReferencesController::class, 'insert'])->name('reference-insert');
        $this->GET_PUT('/reference/{table}/{uint:id}', [ReferencesController::class, 'update'])->name('reference-update');
        $this->GET('/reference/view/{table}/{uint:id}', [ReferencesController::class, 'view'])->name('reference-view');
        $this->DELETE('/reference/delete', [ReferencesController::class, 'delete'])->name('reference-delete');

        $this->GET_POST('/settings', [SettingsController::class, 'index'])->name('settings');
        $this->POST('/settings/files', [SettingsController::class, 'files'])->name('settings-files');
        $this->POST('/settings/mailer', [SettingsController::class, 'mailer'])->name('settings-mailer');
    }
}
