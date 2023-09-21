<?php

namespace Raptor\File;

use codesaur\Router\Router;

class FileRouter extends Router
{
    public function __construct()
    {
        $this->GET('/files', [FilesController::class, 'index'])->name('files');
        $this->GET('/files/datatable/{table}', [FilesController::class, 'datatable'])->name('files-datatable');
        
        $this->POST('/files/{input}/{table}/{uint:id}/{folder}', [FilesController::class, 'post'])->name('files-post');
        
        $this->GET('/private/file', [PrivateFilesController::class, 'read'])->name('private-files-read');
        
        $this->GET('/files/{modal}/{table}', [PrivateFilesController::class, 'modal'])->name('files-modal');
        $this->PUT('/files/{table}/{uint:id}', [PrivateFilesController::class, 'update'])->name('files-update');
        $this->DELETE('/files/{table}', [PrivateFilesController::class, 'delete'])->name('files-delete');
    }
}
