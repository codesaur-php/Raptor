<?php

namespace Raptor\File;

use codesaur\Router\Router;

class FileRouter extends Router
{
    public function __construct()
    {
        $this->GET('/private/file', [PrivateFilesController::class, 'read'])->name('private-file-read');
        $this->POST('/public/file/{input}/{table}/{uint:id}', [PublicFilesController::class, 'post'])->name('public-file-post');
        $this->GET('/files/{modal}/{table}', [PrivateFilesController::class, 'modal'])->name('files-modal');
        $this->PUT('/files/{table}/{uint:id}', [PrivateFilesController::class, 'update'])->name('files-update');
    }
}
