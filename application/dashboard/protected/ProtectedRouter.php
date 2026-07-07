<?php

namespace Dashboard\Protected;

use codesaur\Router\Router;

/**
 * Class ProtectedRouter
 *
 * Protected файл унших route-ийг бүртгэнэ.
 *
 *   GET /dashboard/protected/file?name={folder}/{file}
 *       -> ProtectedFilesController::read()
 *
 * Route нь нэвтэрсэн хэрэглэгчид зориулагдсан ба эрхийн шалгалт нь
 * ProtectedFilesController::authorizeRead() hook дотор хийгдэнэ. DEFAULT нь
 * зөвшөөрөнгүй - нэвтэрсэн дурын хэрэглэгч унших боломжтой (system_coder үргэлж).
 * Тухайн төслийн эмзэг файлыг хамгаалахын тулд authorizeRead()-д эрх/tenant
 * шалгалт нэмэх ёстой (шууд засварлана, эсвэл subclass хийнэ). Мутаци
 * хийдэггүй GET route тул CsrfMiddleware шаардлагагүй.
 *
 * Зөвхөн subclass controller-той болсон үед л энэ route-ийг
 * Dashboard\Application дотор $this->override(...)-оор дарж бичнэ -
 * authorizeRead()-ийг шууд засварласан бол router хөндөгдөхгүй.
 *
 * @package Dashboard\Protected
 */
class ProtectedRouter extends Router
{
    public function __construct()
    {
        $this->GET('/protected/file', [ProtectedFilesController::class, 'read'])
            ->name('protected-file-read');
    }
}
