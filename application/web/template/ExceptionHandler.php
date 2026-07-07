<?php

namespace Web\Template;

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

        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';

        if ($code != 0) {
            // Стандарт HTTP статус кодын мужид (RFC 9110: 100-599) багтаж
            // байвал -> HTTP статус илгээх
            if (\is_numeric($code) && $code >= 100 && $code <= 599 && !\headers_sent()) {
                \http_response_code((int) $code);
            }
        }

        \error_log("$title: $message");

        $vars = [
            'title' => $title,
            'code'  => $code,
            'message' => '<p class="lead mb-4">'
                . \htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        ];

        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<pre class="bg-dark text-light rounded p-3 small">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) . '</pre>';
        }
        
        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
