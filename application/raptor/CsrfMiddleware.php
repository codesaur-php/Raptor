<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class CsrfMiddleware
 *
 * Dashboard-ийн POST/PUT/DELETE хүсэлтүүдэд CSRF token шалгах middleware.
 *
 * Token нь login үед $_SESSION['CSRF_TOKEN'] дотор үүсэж хадгалагдана.
 * Клиент тал (JS) нь X-CSRF-TOKEN header-аар дамжуулна.
 *
 * GET/HEAD/OPTIONS хүсэлтүүд шалгахгүй дамжина.
 * /login замууд мөн exempt (тэнд token үүсдэг).
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Token байхгүй бол үүсгэх (session writable байх ёстой)
        if (empty($_SESSION['CSRF_TOKEN']) && \session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION['CSRF_TOKEN'] = \bin2hex(\random_bytes(32));
        }

        // Token-г request attribute-аар дамжуулна (Controller-д ашиглахад)
        $request = $request->withAttribute('csrf_token', $_SESSION['CSRF_TOKEN'] ?? '');

        $method = $request->getMethod();

        // Safe method -> дамжуулна
        if (\in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        // Path олох (SessionMiddleware-тай ижил логик)
        $path = \rawurldecode($request->getUri()->getPath());
        $scriptRoot = \dirname($request->getServerParams()['SCRIPT_NAME']);
        if (($len = \strlen($scriptRoot)) > 1) {
            $path = \substr($path, $len);
            $path = '/' . \ltrim($path, '/');
        }

        // Login exempt
        if (\str_contains($path, '/login')) {
            return $handler->handle($request);
        }

        // Token шалгах
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
