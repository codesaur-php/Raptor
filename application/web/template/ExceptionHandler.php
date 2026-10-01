<?php

namespace Web\Template;

use codesaur\Template\Markup;
use codesaur\Template\FileTemplate;
use codesaur\Http\Application\ExceptionHandler as Base;
use codesaur\Http\Application\ExceptionHandlerInterface;

/**
 * Class ExceptionHandler
 *
 * Web Layer Exception Handler.
 *
 * @package Web\Template
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable): void
    {
        $errorTemplate = __DIR__ . '/page-404.html';
        if (!\class_exists(FileTemplate::class)
            || !\file_exists($errorTemplate)
        ) {
            (new Base())->exception($throwable);
            return;
        }

        // HTTP статус: стандарт мужид (RFC 9110: 100-599) багтах int code бол
        // түүнийг, бусад (\Error-ийн 0, PDOException-ий SQLSTATE '42S02' гэх
        // мэт) бүгд 500
        $rawCode = $throwable->getCode();
        $code = \is_int($rawCode) && $rawCode >= 100 && $rawCode <= 599 ? $rawCode : 500;
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';

        if (!\headers_sent()) {
            \http_response_code($code);
        }

        // Log файл руу бичих. 404 (олдоогүй хуудас, unknown route) нь ихэвчлэн
        // bot скан тул production дээр бичихгүй - зөвхөн хөгжүүлэлтэд бичнэ.
        // Bot зочлолтын бүртгэл хэрэгтэй бол вэб серверийн access log-оос
        // (Apache/nginx access.log, hosting panel-ийн Raw Access Logs) харна -
        // тэнд бүх 404 хүсэлт IP, User-Agent мэдээллийн хамт хадгалагддаг.
        if ($code != 404 || CODESAUR_DEVELOPMENT) {
            \error_log("$title: $message");
        }

        // 5xx алдааны дотоод мессеж (SQL алдаа, файлын зам гэх мэт) production
        // дээр зочинд харагдахгүй - зөвхөн log-д үлдэнэ
        if ($code >= 500 && !CODESAUR_DEVELOPMENT) {
            $message = 'Something went wrong. Please try again later.';
        }

        // message нь энд бүрэн escape хийгдсэн HTML тул template-ийн autoescape-аас
        // Markup-аар чөлөөлнө. Хэрэглэгчийн орц ($message) htmlspecialchars-аар л орно.
        $html = '<p class="lead mb-4">'
            . \htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</p>';

        if (CODESAUR_DEVELOPMENT) {
            $html .=
                '<pre class="bg-dark text-light rounded p-3 small">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) . '</pre>';
        }

        $vars = [
            'title' => $title,
            'code'  => $code,
            'message' => new Markup($html)
        ];

        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
