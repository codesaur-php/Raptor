<?php

namespace Raptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/languages', [LanguageController::class, 'index'])->name('languages');
        $this->GET('/languages/datatable', [LanguageController::class, 'datatable'])->name('languages-datatable');
        $this->GET_POST('/language', [LanguageController::class, 'insert'])->name('language-insert');
        $this->GET_PUT('/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update');
        $this->GET('/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');
        $this->DELETE('/language', [LanguageController::class, 'delete'])->name('language-delete');
        
        $this->GET('/texts', [TextController::class, 'index'])->name('texts');
        $this->GET('/texts/datatable/{table}', [TextController::class, 'datatable'])->name('text-datatable');
        $this->GET_POST('/text/{table}', [TextController::class, 'insert'])->name('text-insert');
        $this->GET_PUT('/text/{table}/{uint:id}', [TextController::class, 'update'])->name('text-update');
        $this->GET('/text/view/{table}/{uint:id}', [TextController::class, 'view'])->name('text-view');
        $this->DELETE('/text/delete', [TextController::class, 'delete'])->name('text-delete');

        $this->GET('/countries', [CountriesController::class, 'index'])->name('countries');
        $this->GET('/countries/datatable', [CountriesController::class, 'datatable'])->name('countries-datatable');
        $this->GET_POST('/country', [CountriesController::class, 'insert'])->name('country-insert');
        $this->GET_PUT('/country/{id}', [CountriesController::class, 'update'])->name('country-update');
        $this->GET('/country/view/{id}', [CountriesController::class, 'view'])->name('country-view');
        $this->DELETE('/country', [CountriesController::class, 'delete'])->name('country-delete');
    }
}
