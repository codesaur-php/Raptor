<?php

namespace Raptor\Content;

/**
 * Class PrivateFilesController
 *
 * --------------------------------------------------------------
 * Private Files - Secure File Access Controller
 * --------------------------------------------------------------
 * Энэ controller нь серверийн public/ (HTTP-ээр шууд харагддаг)
 * сангаас ялгаатайгаар:
 *
 *      /private
 *
 * хавтсанд байрлах файлуудыг зөвхөн:
 *
 *   нэвтэрсэн хэрэглэгчдэд (authenticated)
 *   permission-тэй үйлдлүүдээр
 *
 * үзүүлэх зориулалттай security-focused controller юм.
 *
 * --------------------------------------------------------------
 * Яагаад энэ controller хэрэгтэй вэ?
 * --------------------------------------------------------------
 * PHP-ийн public фолдерт байрласан файлуудыг хэн ч URL-ээр шууд авч чадна.
 *
 * Харин private дотор байрлах файлууд:
 *
 *   шууд URL-аар татагдахгүй  
 *   зөвхөн read() function-аар дамжин гарч ирнэ  
 *   authentication шалгана  
 *   MIME type тохируулж файл дамжуулна  
 *   лог бүртгэнэ  
 *
 * Энэ нь:
 *   - гэрээ, хувийн PDF  
 *   - иргэний мэдээлэл  
 *   - нууц хавсралт  
 *   - экспортолдог excel/csv  
 *
 * гэх мэт sensitive файлд зориулагдсан.
 *
 * --------------------------------------------------------------
 * FilesController-ийг удамшуулдаг (extends)
 * --------------------------------------------------------------
 *  Тиймээс:
 *   - moveUploaded()
 *   - uniqueName()
 *   - formatSizeUnits()
 *   - dБ ажиллагаа
 *
 * зэрэг бүх файлын менежментийн боломжуудыг өвлөн ашиглана.
 *
 * --------------------------------------------------------------
 * @package Raptor\Content
 */
class PrivateFilesController extends FilesController
{
    /**
     * Private folder-д зориулсан фолдерын замыг тогтооно.
     *
     * ----------------------------------------------------------
     * /private/{folder} -> серверийн доторх бодит зам (local)
     * /private/file?name={folder}/{file} -> клиентэд харагдах public URL
     *
     * private файлуудыг public URL-аар шууд гаргахгүй!
     *   -> зөвхөн read() function-аар дамжина.
     *
     * cache/ хавтас хамгаалагдсан - RuntimeException (403) шиднэ.
     *
     * @param string $folder     Файл хадгалах хавтас
     */
    public function setFolder(string $folder)
    {
        if (\str_starts_with(\trim($folder, '/'), 'cache')) {
            throw new \RuntimeException('Cache folder is protected', 403);
        }
        $this->local_folder = $this->getDocumentPath("/../private{$folder}");
        $this->public_path = "{$this->getScriptPath()}/private/file?name=$folder";
    }

    /**
     * Private файлын public path-ийг (read() API-аар дамжих) буцаана.
     *
     * @param string $fileName
     * @return string
     */
    public function getFilePublicPath(string $fileName): string
    {
        return "$this->public_path/" . \urlencode($fileName);
    }

    /**
     * Private хавтас доторх файлыг хэрэглэгчид securely дамжуулах.
     *
     * ----------------------------------------------------------
     * Authentication шалгана  
     * Query string-оор ирсэн name параметрийг шалгана  
     * Файл үнэхээр private фолдерт байгаа эсэхийг шалгана  
     * MIME төрлийг mime_content_type() ашиглан тодорхойлно  
     * Файлыг readfile() ашиглан дамжуулна  
     *
     * HTTP header-ийг зөв тохируулж өгөхгүй бол файл буруу харагдана.
     *
     * ----------------------------------------------------------
     * Security Notes
     * ----------------------------------------------------------
     *  - Private файлуудыг шууд /uploads/ гэх мэт замаар өгдөггүй
     *  - Зөвхөн read() -> authentication -> файлыг унших -> буцаах
     *  - Directory traversal халдлагаас хамгаална
     *      ../ болон бусад тэмдэгтүүдийг getDocumentPath() автоматаар цэвэрлэдэг
     *  - private/cache/ хавтас руу хандахыг хориглоно
     *
     * ----------------------------------------------------------
     * @throws Exception:
     *      401 -> Unauthorized  
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
            $filePath = $this->getDocumentPath('/../private' . $fileName);
            if (empty($fileName) || !\file_exists($filePath)) {
                throw new \Exception('Not Found', 404);
            }

            // Cache folder-т хандахыг хориглох
            $privateDir = $this->getDocumentPath('/../private');
            $cacheDir = $privateDir . '/cache';
            if (\str_starts_with(\realpath($filePath) ?: $filePath, \realpath($cacheDir) ?: $cacheDir)) {
                throw new \Exception('Forbidden', 403);
            }

            // Системийн чухал файлуудыг уншихаас хамгаалах
            $basename = \strtolower(\basename($filePath));
            $ext = \strtolower(\pathinfo($filePath, \PATHINFO_EXTENSION));
            $blockedExtensions = ['php', 'phtml', 'phar', 'sh', 'bat', 'cmd', 'exe', 'ini', 'log', 'sql'];
            $blockedFiles = ['.env', '.htaccess', '.htpasswd', '.gitignore', 'composer.json', 'composer.lock'];
            if (\in_array($ext, $blockedExtensions, true)
                || \in_array($basename, $blockedFiles, true)
                || \str_starts_with($basename, '.env')
            ) {
                throw new \Exception('Forbidden', 403);
            }

            $mimeType = \mime_content_type($filePath);
            if ($mimeType === false) {
                throw new \Exception('No Content', 204);
            }

            \header("Content-Type: $mimeType");
            \readfile($filePath);
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            $this->headerResponseCode($err->getCode());
        }
    }
}
