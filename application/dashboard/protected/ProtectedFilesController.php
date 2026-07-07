<?php

namespace Dashboard\Protected;

use Raptor\Content\FilesController;

/**
 * Class ProtectedFilesController
 *
 * --------------------------------------------------------------
 * Protected Files - Secure File Access (reference implementation)
 * --------------------------------------------------------------
 * Энэ controller нь серверийн public/ (HTTP-ээр шууд харагддаг)
 * сангаас ялгаатайгаар document root-оос гадуур байрлах
 *
 *      /protected
 *
 * хавтасны файлуудыг зөвхөн read() API-аар, эрхийн шалгалттайгаар
 * дамжуулах зориулалттай.
 *
 * --------------------------------------------------------------
 * Яагаад application/dashboard дотор байгаа вэ?
 * --------------------------------------------------------------
 * Raptor core (application/raptor)-ийн ямар ч модуль protected
 * storage ашигладаггүй - Raptor-тай хамт ирдэг бүх модуль (news, pages,
 * products, organizations, settings, dev-requests) файлаа /public-д
 * хадгалдаг. Protected файл нь бүхэлдээ төслийн шийдвэр тул энэ
 * controller-ийг төсөл бүрт өөрчлөгдөх магадлал өндөр кодын хамт
 * application/dashboard-д байрлуулсан - reference implementation.
 * Төсөл болгон өөрийн эрх/tenant дүрмээ authorizeRead()-д бичиж ашиглана.
 *
 * --------------------------------------------------------------
 * ЭРХИЙН ШАЛГАЛТ - authorizeRead() hook
 * --------------------------------------------------------------
 * read() нь файл дамжуулахаас өмнө authorizeRead($relativePath)
 * дуудна. Default зан төлөв нь permissive: нэвтэрсэн хэрэглэгч бүр
 * protected файлыг уншиж болно. Эмзэг файл (гэрээ, иргэний мэдээлэл,
 * нууц хавсралт) гаргах бол authorizeRead()-ээ өөрийн модулийн
 * index/view permission эсвэл tenant-ownership дүрмээр нарийсгаж бичнэ.
 * Хамгийн энгийн зам нь authorizeRead()-ийн body-г энд шууд засварлах.
 * Энэ файлаа өөрчлөхгүй үлдээхийг хүсвэл subclass хийж бас болно:
 *
 *   class MyProtectedFilesController extends ProtectedFilesController
 *   {
 *       protected function authorizeRead(string $relativePath): bool
 *       {
 *           // /contracts/{orgId}/... -> зөвхөн тухайн байгууллагын файл.
 *           // Таарахгүй бол Exception шидэж, таарвал true буцаана.
 *           $orgId = (int) (\explode('/', $relativePath)[1] ?? 0);
 *           if ($orgId !== (int) $this->getUser()->organization['id']) {
 *               throw new \Exception('Forbidden', 403);
 *           }
 *           return true;
 *       }
 *   }
 *
 * Subclass замыг сонгосон үед л ProtectedRouter-ийн route-ийг өөрийн
 * controller руу override хийнэ ($app->override(...)) - шууд засварласан
 * бол route өөрчлөх юу ч байхгүй.
 *
 * --------------------------------------------------------------
 * FilesController-ийг удамшуулдаг (extends)
 * --------------------------------------------------------------
 * Тиймээс moveUploaded(), uniqueName(), formatSizeUnits() зэрэг
 * бүх файлын менежментийн боломжийг өвлөн ашиглана (protected
 * хавтас руу upload хийхэд).
 *
 * @package Dashboard\Protected
 */
class ProtectedFilesController extends FilesController
{
    /**
     * Protected folder-д зориулсан фолдерын замыг тогтооно.
     *
     * ----------------------------------------------------------
     * /protected/{folder}                  -> серверийн доторх бодит зам (local)
     * /protected/file?name={folder}/{file} -> клиентэд харагдах public URL
     *
     * protected файлуудыг public URL-аар шууд гаргахгүй!
     *   -> зөвхөн read() function-аар дамжина.
     *
     * @param string $folder     Файл хадгалах хавтас
     */
    public function setFolder(string $folder)
    {
        $this->local_folder = $this->getDocumentPath("/../protected{$folder}");
        // Named route ашиглан mount-aware public URL үүсгэнэ. generateRouteLink()
        // нь getScriptPath() (subdirectory) болон mount prefix (/dashboard)-ийг
        // авто нэмдэг тул энд гараар бичих шаардлагагүй.
        $this->public_path = $this->generateRouteLink('protected-file-read') . "?name=$folder";
    }

    /**
     * Protected файлын public path-ийг (read() API-аар дамжих) буцаана.
     *
     * @param string $fileName
     * @return string
     */
    public function getFilePublicPath(string $fileName): string
    {
        return "$this->public_path/" . \urlencode($fileName);
    }

    /**
     * Protected файл уншихыг зөвшөөрөх эсэхийг шийдэх hook.
     *
     * Default зан төлөв нь permissive: нэвтэрсэн хэрэглэгч бүр protected
     * файлыг уншиж болно. Тодорхой хандалтын дүрэм хэрэгтэй бол энэ
     * controller-ийг subclass хийж эсвэл доорх method body-г шууд засварлаж,
     * commented жишээ шиг өөрийн модулийн permission эсвэл tenant-ownership
     * дүрмээр нарийсгана.
     *
     * @param string $relativePath  protected root-оос хойших зам,
     *                              forward slash-аар (ж: 'contracts/5/a.pdf')
     * @return bool  true бол унших зөвшөөрнө
     */
    protected function authorizeRead(string $relativePath): bool
    {
        // system_coder бол cross-tenant superuser - бүх protected файлыг унших эрхтэй.
        if ($this->isUser('system_coder')) {
            return true;
        }

        // Default: нэвтэрсэн хэрэглэгч бүр protected файлыг уншиж болно.
        // (read() энэ hook-ийг дуудахаас өмнө isUserAuthorized()-ийг шалгасан
        // байдаг тул энд хүрч ирсэн хэрэглэгч заавал нэвтэрсэн байна.)
        //
        // Хандалтыг нарийсгах бол энэ controller-ийг subclass хийж эсвэл
        // доорх return-ийг энд шууд өөрийн дүрмээр солино. Жишээ нь файл нь нэвтэрсэн
        // хэрэглэгчийн байгууллагад харьяалагдаж байгаа эсэхийг шалгаж,
        // таарахгүй бол Exception шидэж, таарвал true буцаах:
        //
        //   // /{orgId}/... -> файл {orgId} байгууллагынх
        //   $orgId = (int) (\explode('/', $relativePath)[1] ?? 0);
        //   if ($orgId !== (int) $this->getUser()->organization['id']) {
        //       // read()-ийн try/catch энэ code-ийг HTTP статус болгож буцаана
        //       throw new \Exception('Forbidden', 403);
        //   }
        //   return true;
        //
        // эсвэл модулийн index permission-ээр (false -> read() 403 шиднэ):
        //
        //   return $this->isUserCan('system_content_index');

        return true;
    }

    /**
     * Protected хавтас доторх файлыг хэрэглэгчид securely дамжуулах.
     *
     * ----------------------------------------------------------
     * Authentication шалгана (нэвтэрсэн эсэх)
     * authorizeRead() hook-оор эрхийн шалгалт хийнэ (default: нэвтэрсэн хэрэглэгч)
     * Query string-оор ирсэн name параметрийг шалгана
     * Directory traversal-аас хамгаална (realpath containment)
     * Системийн эмзэг файлуудыг (php, .env, .htaccess г.м.) блоклоно
     * MIME төрлийг тодорхойлж readfile()-аар дамжуулна
     *
     * ----------------------------------------------------------
     * @throws Exception:
     *      401 -> Unauthorized (нэвтрээгүй)
     *      403 -> Forbidden (эрхгүй / traversal / blocked)
     *      404 -> File not found
     *      204 -> Mime type тодорхойлогдоогүй
     *
     * @return void
     */
    public function read()
    {
        try {
            // Хэрэглэгч нэвтэрсэн байх ёстой
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            // URL parameter: ?name=/folder/file.ext
            $fileName = $this->getQueryParams()['name'] ?? '';
            if (empty($fileName)) {
                throw new \Exception('Not Found', 404);
            }

            // Хүссэн файлын бодит (canonical) замыг тооцоолно. realpath() нь
            // бүх ../, symlink, давхар slash-ийг шийддэг тул цаашид зөвхөн
            // $realFile-ийг ашиглана. getDocumentPath() нь string-ийг шууд
            // холбодог тул ../-ийг өөрөө цэвэрлэдэггүй. is_file() нь хавтас
            // биш, нэрээр нь үнэхээр файл байгааг баталгаажуулна.
            $protectedDir = $this->getDocumentPath('/../protected');
            $filePath = $this->getDocumentPath('/../protected' . $fileName);
            $realProtected = \realpath($protectedDir);
            $realFile = \realpath($filePath);
            if ($realFile === false || !\is_file($realFile)) {
                throw new \Exception('Not Found', 404);
            }

            // Directory traversal-аас хамгаалах (containment): уншиж буй файл
            // заавал protected хавтсын дотор байх ёстой. ж: name=/../../../
            // somesecret/info.txt гэх мэт оролдлогыг блоклоно. Trailing
            // DIRECTORY_SEPARATOR нь /protected-evil зэрэг prefix-collision
            // bypass-аас сэргийлнэ.
            if ($realProtected === false
                || !\str_starts_with($realFile, $realProtected . \DIRECTORY_SEPARATOR)
            ) {
                throw new \Exception('Forbidden', 403);
            }

            // Эрхийн шалгалт (authorizeRead hook). protected root-оос хойших
            // замыг forward slash-аар дамжуулна (subclass дотор задлахад
            // OS-аас үл хамааралтай байлгах).
            $relativePath = \str_replace(
                '\\',
                '/',
                \substr($realFile, \strlen($realProtected) + 1)
            );
            if (!$this->authorizeRead($relativePath)) {
                throw new \Exception('Forbidden', 403);
            }

            // Системийн чухал файлуудыг уншихаас хамгаалах
            $basename = \strtolower(\basename($realFile));
            $ext = \strtolower(\pathinfo($realFile, \PATHINFO_EXTENSION));
            $blockedExtensions = ['php', 'phtml', 'phar', 'sh', 'bat', 'cmd', 'exe', 'ini', 'log', 'sql'];
            $blockedFiles = ['.env', '.htaccess', '.htpasswd', '.gitignore', 'composer.json', 'composer.lock'];
            if (\in_array($ext, $blockedExtensions, true)
                || \in_array($basename, $blockedFiles, true)
                || \str_starts_with($basename, '.env')
            ) {
                throw new \Exception('Forbidden', 403);
            }

            $mimeType = \mime_content_type($realFile);
            if ($mimeType === false) {
                throw new \Exception('No Content', 204);
            }

            \header("Content-Type: $mimeType");
            // URL нь өргөтгөлгүй (/protected/file) тул filename өгснөөр татах үед
            // зөв нэр/өргөтгөлтэй болж хадгалагдана (дэмждэг браузер inline харуулна).
            \header('Content-Disposition: inline; filename="' . \basename($realFile) . '"');
            \header('Content-Length: ' . \filesize($realFile));
            \readfile($realFile);
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            $this->headerResponseCode($err->getCode());
        }
    }
}
