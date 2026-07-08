<?php

namespace Dashboard\File;

use codesaur\Router\Router;

use Dashboard\CsrfMiddleware;

/**
 * Class FileRouter
 *
 * Файлын модулийн маршрутууд: файлын менежмент (/files) болон
 * protected файл унших (/protected/file).
 *
 * @package Dashboard\File
 */
class FileRouter extends Router
{
    public function __construct()
    {
        /* ------------------------------
         * FILES - Файлын менежмент
         * ------------------------------ */

        // Файлуудын үндсэн хуудас
        $this->GET('/files', [FilesController::class, 'index'])->name('files');

        // Файлын модуль/төрөл тус бүрийн жагсаалт JSON
        $this->GET('/files/list/{table}', [FilesController::class, 'list'])->name('files-list');

        // Файл upload хийх
        $this->POST('/files/upload', [FilesController::class, 'upload'])->name('files-upload')->middleware([CsrfMiddleware::class]);

        // Файл upload хийгээд мэдээллийн сан хүснэгтэд бүртгэх
        $this->POST('/files/post/{table}', [FilesController::class, 'post'])->name('files-post')->middleware([CsrfMiddleware::class]);

        // Файл сонгох modal UI
        $this->GET('/files/modal/{table}', [FilesController::class, 'modal'])->name('files-modal');

        // Файлын мэдээлэл шинэчлэх (partial update)
        $this->PATCH('/files/{table}/{uint:id}', [FilesController::class, 'update'])->name('files-update')->middleware([CsrfMiddleware::class]);

        // Файлыг устгах
        $this->DELETE('/files/{table}/delete', [FilesController::class, 'delete'])->name('files-delete')->middleware([CsrfMiddleware::class]);

        /* ------------------------------
         * PROTECTED - Хамгаалагдсан файл унших
         * ------------------------------ */

        // GET /dashboard/protected/file?name={folder}/{file}
        //     -> ProtectedFilesController::read()
        //
        // Route нь нэвтэрсэн хэрэглэгчид зориулагдсан ба эрхийн шалгалт нь
        // ProtectedFilesController::authorizeRead() hook дотор хийгдэнэ.
        // DEFAULT нь нэвтэрсэн дурын хэрэглэгч унших боломжтой (system_coder бол үргэлж).
        // Тухайн төслийн эмзэг файлыг хамгаалахын тулд authorizeRead()-д эрх/tenant шалгалтаа
        // шууд бичиж нэмэх ёстой. Мутаци хийдэггүй GET route тул CsrfMiddleware шаардлагагүй.
        $this->GET('/protected/file', [ProtectedFilesController::class, 'read'])->name('protected-file-read');
    }
}
