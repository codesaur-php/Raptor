<?php

namespace Dashboard\Exception;

use codesaur\Template\FileTemplate;
use codesaur\Http\Application\ExceptionHandler as Base;
use codesaur\Http\Application\ExceptionHandlerInterface;

/**
 * Class ErrorHandler
 * -----------------------------------------------
 * Raptor Framework-ийн default алдаа баригч (Exception Handler).
 *
 * Энэ класс нь систем дотор гарсан бүх төрлийн Exception болон Error-ийг
 * нэг цэгээс барьж, хэрэглэгчид харагдах энгийн error хуудас руу рендерлэнэ.
 *
 * Хэрэв `/error.html` template байгаа бол -> HTML алдааны хуудас руу рендерлэнэ  
 * Хэрэв template байхгүй эсвэл Template engine ачаалагдаагүй бол ->  
 *   `codesaur\Http\Application\ExceptionHandler` үндсэн fallback ашиглана  
 *
 * Алдаа барих үндсэн логик:
 *   1) Throwable объектын код, мессежийг унших
 *   2) HTTP статус код тохируулах (100-599 мужийн стандарт код)
 *   3) error.log руу бичих (`error_log`) - 404-ийг production дээр алгасна
 *   4) error.html template рендерлэх
 *
 * Хөгжүүлэлтийн горимд (CODESAUR_DEVELOPMENT):
 *   -> Stack trace дэлгэц дээр JSON форматтай харуулна.
 *
 * Template-д дамжуулах хувьсагчууд:
 *   - title     - Алдааны гарчиг (Exception 404 гэх мэт)
 *   - message   - Хэрэглэгчид үзэгдэх аюулгүй алдааны текст
 *   - return    - Буцах линк тэмдэглэгээ
 *
 * @package Dashboard\Exception
 */
class ErrorHandler implements ExceptionHandlerInterface
{
    /**
     * Глобал exception барих гол функц.
     *
     * @param \Throwable $throwable Баригдсан Exception/Error
     *
     * @return mixed HTML render эсвэл fallback Exception handler
     */
    public function exception(\Throwable $throwable): void
    {
        $errorTemplate = __DIR__ . '/error.html';

        // Хэрэв FileTemplate эсвэл template файл байхгүй бол fallback ашиглах
        if (!\class_exists(FileTemplate::class)
            || !\file_exists($errorTemplate)
        ) {
            (new Base())->exception($throwable);
            return;
        }

        // Exception мэдээлэл
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';

        // Алдааны код 0 биш бол тайлбарт нэмж харуулна
        if ($code != 0) {
            $title .= " $code";

            // Стандарт HTTP статус кодын мужид (RFC 9110: 100-599) багтаж
            // байвал -> HTTP статус илгээх
            if (\is_numeric($code) && $code >= 100 && $code <= 599 && !\headers_sent()) {
                \http_response_code((int) $code);
            }
        }

        // Log файл руу бичих. 404 (олдоогүй хуудас, unknown route) нь ихэвчлэн
        // bot скан тул production дээр бичихгүй - зөвхөн хөгжүүлэлтэд бичнэ.
        // Bot зочлолтын бүртгэл хэрэгтэй бол вэб серверийн access log-оос
        // (Apache/nginx access.log, hosting panel-ийн Raw Access Logs) харна
        // - тэнд бүх 404 хүсэлт IP, User-Agent мэдээллийн хамт хадгалагддаг.
        if ($code != 404 || CODESAUR_DEVELOPMENT) {
            \error_log("$title: $message");
        }

        // Host тодорхойлох
        $host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || $_SERVER['SERVER_PORT'] == 443)
                ? 'https://' : 'http://';
        $host .= $_SERVER['HTTP_HOST'] ?? 'localhost';

        $vars = [
            'title' => $title,
            'return' => 'Return to host',
            'message' => '<h3 style="text-align:center;color:white">' . \htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</h3>'
        ];

        // Хөгжүүлэлтийн горимд stack trace харуулах
        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<br/><pre style="color:white;height:300px;overflow-y:auto;overflow-x:hidden;">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT)
                . '</pre>';
        }

        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
