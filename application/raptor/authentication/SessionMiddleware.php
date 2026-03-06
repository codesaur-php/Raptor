<?php

namespace Raptor\Authentication;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Session middleware - HTTP session-г зохицуулах үндсэн давхарга.
 *
 * Энэхүү middleware нь Raptor системийн бүх хүсэлт дээр ажиллана
 * (login, dashboard, API гэх мэт) бөгөөд session нээх, cookie хугацаа,
 * write-lock удирдах зэргийг хариуцна.
 *
 * Үндсэн зорилго:
 * ---------------------------------------------------------------
 * 1) Session нэрийг "raptor" болгон тогтмолжуулах
 *
 * 2) Session идэвхгүй байвал нээх
 *      - Cookie lifetime = 30 хоног
 *      - \session_start() - зөвхөн анхны хүсэлтэд
 *
 * 3) Session write-lock оптимизаци:
 *      - Login-той холбоотой route-уудаас бусад үед
 *        session write-access шаардлагагүй.
 *      - Тиймээс write-lock-ийг суллахын тулд:
 *
 *          \session_write_close();
 *
 *      - Ингэснээр:
 *          * request concurrency сайжирна
 *          * PHP session file lock deadlock-оос сэргийлнэ
 *          * API болон dashboard хүсэлтүүд хоорондоо бөглөрөхгүй
 *
 * 4) request attributes -> unchanged
 *      Middleware нь зөвхөн session layer-т нөлөөлнө,
 *      authentication, localization, router-д нөлөөлөхгүй.
 *
 * Security онцлогууд:
 * ---------------------------------------------------------------
 * - Session-г зөвхөн login хуудсан дээр write-lock хийж нээлттэй үлдээдэг.
 * - Бусад бүх route дээр write-lock-ийг эрт сулладаг (read-only болгож).
 * - Энэ нь session fixation болон session blocking халдлагыг багасгана.
 *
 * Performance онцлогууд:
 * ---------------------------------------------------------------
 * - PHP session file lock нь нэг request-г дараагийн request-ээс
 *   түгждэг (blocking behavior).
 * - session_write_close() нь үүнийг шийдэж, өндөр ачаалалтай системд
 *   асар том давуу тал өгдөг.
 *
 */
class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Session эхлүүлж, write-lock оптимизацийг хийсний дараа дараагийн handler рүү дамжуулах.
     *
     * @param ServerRequestInterface $request HTTP хүсэлт.
     * @param RequestHandlerInterface $handler Дараагийн middleware/handler.
     * @return ResponseInterface HTTP хариу.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1) Session нэрийг тогтмолжуулах
        \session_name('raptor');

        // 2) Session нээлттэй эсэхийг шалгах
        if (\session_status() != \PHP_SESSION_ACTIVE) {
            $lifetime = \time() + 30 * 24 * 60 * 60; // 30 хоног
            \session_set_cookie_params($lifetime);
            \session_start();
        }

        // 3) Session write-lock оптимизаци
        if (\session_status() == \PHP_SESSION_ACTIVE) {
            $path = \rawurldecode($request->getUri()->getPath());

            $script_root = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($lngth = \strlen($script_root)) > 1) {
                $path = \substr($path, $lngth);
                $path = '/' . \ltrim($path, '/');
            }

            // Session-г зөвхөн "/login" route дээр write-open байлгана
            // Бусад route дээр даруй write-lock -> close -> concurrency сайжирна
            if (!\str_contains($path, '/login')) {
                \session_write_close();
            }
        }

        // 4) Дараагийн middleware рүү дамжуулах
        return $handler->handle($request);
    }
}
