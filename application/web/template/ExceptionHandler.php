<?php

namespace Web\Template;

use codesaur\Template\FileTemplate;
use codesaur\Http\Message\ReasonPrhase;
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
            'message' => '<p class="lead mb-4">'
                . \htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        ];

        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<pre class="bg-dark text-light rounded p-3 small">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT) . '</pre>';
        }
        
        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
