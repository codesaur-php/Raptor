<?php

namespace Raptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    public function __construct()
    {
        $this->GET('/references', [ReferencesController::class, 'index'])->name('references');
        $this->GET('/references/datatable/{table}', [ReferencesController::class, 'datatable'])->name('reference-datatable');
        $this->GET_POST('/references/{table}', [ReferencesController::class, 'insert'])->name('reference-insert');
        $this->GET_PUT('/references/{table}/{uint:id}', [ReferencesController::class, 'update'])->name('reference-update');
        $this->GET('/references/view/{table}/{uint:id}', [ReferencesController::class, 'view'])->name('reference-view');
        $this->DELETE('/references/delete', [ReferencesController::class, 'delete'])->name('reference-delete');

        $this->GET_POST('/settings', [SettingsController::class, 'index'])->name('settings');
        $this->POST('/settings/files', [SettingsController::class, 'files'])->name('settings-files');
        $this->POST('/settings/mailer', [SettingsController::class, 'mailer'])->name('settings-mailer');
    
        $this->GET('/pages', [PagesController::class, 'index'])->name('pages');
        $this->GET('/pages/datatable', [PagesController::class, 'datatable'])->name('pages-datatable');
        $this->GET_POST('/pages/insert', [PagesController::class, 'insert'])->name('page-insert');
        $this->GET_PUT('/pages/{uint:id}', [PagesController::class, 'update'])->name('page-update');
        $this->GET('/pages/view/{uint:id}', [PagesController::class, 'view'])->name('page-view');
        $this->DELETE('/pages', [PagesController::class, 'delete'])->name('page-delete');

        $this->GET('/news', [NewsController::class, 'index'])->name('news');
        $this->GET('/news/datatable', [NewsController::class, 'datatable'])->name('news-datatable');
        $this->GET_POST('/news/insert', [NewsController::class, 'insert'])->name('news-insert');
        $this->GET_PUT('/news/{uint:id}', [NewsController::class, 'update'])->name('news-update');
        $this->GET('/news/view/{uint:id}', [NewsController::class, 'view'])->name('news-view');
        $this->DELETE('/news', [NewsController::class, 'delete'])->name('news-delete');
    }
}
