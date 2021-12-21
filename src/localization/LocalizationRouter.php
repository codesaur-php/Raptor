<?php

namespace Raptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    function __construct()
    {
        $this->GET('/languages', [LanguageController::class, 'index'])->name('languages');
        $this->GET('/languages/datatable', [LanguageController::class, 'datatable'])->name('languages-datatable');
        $this->GET_POST('/language/insert', [LanguageController::class, 'insert'])->name('language-insert');
        //$this->GET_POST('/language/update/{uint:id}', [LanguageController::class, 'update'])->name('language-update');
        $this->GET('/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');
        //$this->POST('/language/delete', [LanguageController::class, 'delete'])->name('language-delete');
    }
}
