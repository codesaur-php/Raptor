<?php

namespace Raptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/localization', [LocalizationController::class, 'index'])->name('localization');
        
        $this->GET_POST('/language', [LanguageController::class, 'insert'])->name('language-insert');
        $this->GET('/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');
        $this->GET_PUT('/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update');
        $this->DELETE('/language', [LanguageController::class, 'delete'])->name('language-delete');
        
        $this->GET_POST('/text/{table}', [TextController::class, 'insert'])->name('text-insert');
        $this->GET_PUT('/text/{table}/{uint:id}', [TextController::class, 'update'])->name('text-update');
        $this->GET('/text/view/{table}/{uint:id}', [TextController::class, 'view'])->name('text-view');
        $this->DELETE('/text/delete', [TextController::class, 'delete'])->name('text-delete');
    }
}
