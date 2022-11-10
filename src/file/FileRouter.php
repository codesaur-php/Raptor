<?php

namespace Raptor\File;

use codesaur\Router\Router;

class FileRouter extends Router
{
    function __construct()
    {
        $this->GET('/private/file', [PrivateFileController::class, 'read'])->name('private-file-read');
    }
}
