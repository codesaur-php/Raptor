<?php

namespace Raptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    function __construct()
    {
        $this->GET('/languages', [LanguageController::class, 'index'])->name('languages');
        $this->GET('/languages/datatable', [LanguageController::class, 'datatable'])->name('languages-datatable');
        $this->GET_POST('/language', [LanguageController::class, 'insert'])->name('language-insert');
        $this->GET_PUT('/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update');
        $this->GET('/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');
        $this->DELETE('/language', [LanguageController::class, 'delete'])->name('language-delete');
    }
}
