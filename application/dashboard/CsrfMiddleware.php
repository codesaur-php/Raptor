<?php

namespace Dashboard;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class CsrfMiddleware
 *
 * Dashboard-ийн mutating (POST/PUT/PATCH/DELETE) хүсэлтүүдэд CSRF token шалгах
 * per-route middleware.
 *
 * Router дээр mutating route бүрд `->middleware([CsrfMiddleware::class])`
 * хэлбэрээр наагдана. Token нь login үед $_SESSION['CSRF_TOKEN'] дотор үүсэж,
 * dashboard layout-ийн <meta name="csrf-token">-аар клиентэд хүрнэ. Клиент тал
 * (JS) X-CSRF-TOKEN header-аар буцааж дамжуулна.
 *
 * GET/HEAD/OPTIONS (safe method) хүсэлт шалгахгүй дамжина - GET_POST зэрэг
 * compound route-ийн GET талыг блоклохгүйн тулд.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Safe method -> шалгахгүй дамжина (compound route-ийн GET талыг хамгаална)
        if (\in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $sessionToken = $_SESSION['CSRF_TOKEN'] ?? '';
        $headerToken  = $request->getHeaderLine('X-CSRF-TOKEN');
        if (empty($sessionToken) || empty($headerToken) || !\hash_equals($sessionToken, $headerToken)) {
            if (!\headers_sent()) {
                \http_response_code(403);
                \header('Content-Type: application/json');
            }
            echo \json_encode([
                'status'  => 'error',
                'type'    => 'danger',
                'message' => 'CSRF token mismatch. Please reload the page.'
            ]);
            exit;
        }

        return $handler->handle($request);
    }
}
