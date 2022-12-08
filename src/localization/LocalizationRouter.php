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
        
        $this->GET('/texts', [TextController::class, 'index'])->name('texts');
        $this->GET('/texts/datatable/{table}', [TextController::class, 'datatable'])->name('texts-datatable');
        $this->GET_POST('/texts/{table}', [TextController::class, 'insert'])->name('texts-insert');
        $this->GET_PUT('/texts/{table}/{uint:id}', [TextController::class, 'update'])->name('texts-update');
        $this->GET('/texts/view/{table}/{uint:id}', [TextController::class, 'view'])->name('texts-view');
        $this->DELETE('/texts/{table}', [TextController::class, 'delete'])->name('texts-delete');
    }
}
