<?php

namespace Web\Template;

use codesaur\Template\FileTemplate;
use codesaur\Http\Message\ReasonPrhase;
use codesaur\Http\Application\ExceptionHandler as Base;
use codesaur\Http\Application\ExceptionHandlerInterface;

/**
 * Class ExceptionHandler
 * -------------------------------------------------------------
 * Web Layer Exception Handler (Raptor Web Template Module)
 *
 * Энэ класс нь **Public Website (Frontend Web)** хэсгийн алдааг барьж,
 * хэрэглэгчид харагдах зориулалтын зөөлөн (friendly) error page
 * руу рендерлэдэг.
 *
 * Dashboard талын ExceptionHandler-тай харьцуулахад:
 *    Илүү энгийн, минимал view ашиглана  
 *    Debug mode-д stack trace харуулна  
 *    template html файл байхгүй тохиолдолд codesaur-ын үндсэн ExceptionHandler руу fallback хийнэ  
 *
 * -------------------------------------------------------------
 * Ашиглагдах template:
 *      /Web/Template/page-404.html
 *
 * Хэрэв дээрх файл байхгүй бол:
 *      -> `codesaur\Http\Application\ExceptionHandler` fallback ажиллана.
 *
 * -------------------------------------------------------------
 * Алдаа боловсруулах үе шат:
 * -------------------------------------------------------------
 * 1) Throwable -> код, мессеж, төрөл (Exception/Error) унших  
 * 2) HTTP статус кодыг ReasonPhrase ашиглан тохируулах  
 * 3) `error_log()` ашиглан системийн лог дээр бичих  
 * 4) `page-404.html` темплейтэд дараах хувьсагчдыг дамжуулах:  
 *
 *      * title   - Алдааны гарчиг  
 *      * code    - HTTP / Exception код  
 *      * message - Хэрэглэгчид зориулсан HTML message  
 *
 * 5) Хөгжүүлэлтийн горим (CODESAUR_DEVELOPMENT=true) үед:
 *      -> JSON pretty trace-г дэлгэцэн дээр хэвлэж өгнө  
 *
 * -------------------------------------------------------------
 * Хөгжүүлэгчдэд зориулсан зөвлөгөө
 * -------------------------------------------------------------
 * * Web layer нь ихэвчлэн олон нийтэд харагдах контент тул  
 *   нарийн debugging мэдээллийг зөвхөн DEV горимд л харуулна.
 *
 * * Хэрэв сайтын алдааны дизайн / UX өөрчлөх бол:
 *      -> зөвхөн `page-404.html` файлыг өөрчлөхөд хангалттай.
 *
 * * Хэрэв өөр custom Web exception handler үүсгэн ашиглах бол,
 *   Application::__construct() дотор:
 *
 *      $this->use(new MyCustomExceptionHandler());
 *
 *   гэж сольж хэрэглэнэ.
 *
 * -------------------------------------------------------------
 * @package Web\Template
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Throwable-г template ашиглан хэрэглэгчид ээлтэй алдааны хуудас харуулах.
     *
     * @param \Throwable $throwable Үүссэн алдаа эсвэл exception
     * @return void
     */
    public function exception(\Throwable $throwable): void
    {
        $errorTemplate = __DIR__ . '/page-404.html';
        if (!\class_exists(FileTemplate::class)
            || !\file_exists($errorTemplate)
        ) {
            (new Base())->exception($throwable);
            return;
        }

        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';
        
        if ($code != 0) {
            if (\class_exists(ReasonPrhase::class)) {
                $status = "STATUS_$code";
                $reasonPhrase = ReasonPrhase::class;
                if (\defined("$reasonPhrase::$status") && !\headers_sent()) {
                    \http_response_code($code);
                }
            }
        }

        \error_log("$title: $message");
        
        $vars = [
            'title' => $title,
            'code'  => $code,
            'message' => '<h3>' . \htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</h3>'
        ];
        
        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<br/><pre style="text-align:left;height:500px;overflow-y:auto;overflow-x:hidden;">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT) . '</pre>';
        }
        
        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
