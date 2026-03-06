<?php

namespace Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SessionMiddleware
 * -------------------------------------------------------------
 * Web Layer Session Middleware  
 * Raptor Framework-ийн WEB хэсэгт ашиглагдах session удирдлагын middleware.
 *
 * Энэ middleware нь олон хэрэглэгчтэй веб төсөл дээр session lock deadlock,
 * race-condition болон илүүдэл блоклолт үүсэхээс сэргийлэх зорилготой.
 *
 * -------------------------------------------------------------
 * Үндсэн үүрэг
 * -------------------------------------------------------------
 * 1) Session-ийн нэрийг `raptor` болгон тохируулах  
 * 2) Хэрэв session идэвхгүй бол - 30 хоногийн хугацаатай cookie үүсгэнэ  
 * 3) Session эхлүүлсний дараа:
 *      `/language/...` route дээр бол session *write* нээлттэй үлдээнэ  
 *         -> учир нь хэл солих үед `_SESSION` дээр бичлэг хийгддэг  
 *
 *      бусад бүх route дээр:
 *         -> `session_write_close()` дуудан session-г LOCK-гүй болгоно  
 *
 * -------------------------------------------------------------
 * ! Яагаад session_write_close() ашиглаж байгаа вэ?
 * -------------------------------------------------------------
 * PHP session нь default байдлаараа FILE-BASED LOCK ашигладаг.
 * Энэ нь нэг хэрэглэгч олон request зэрэг илгээх үед:
 *
 *   дараагийн request нь өмнөх request unlock болтол хүлээх  
 *
 * гэсэн сул талтай -> веб хурд удааширдаг.
 *
 * Энэ middleware:
 *   -> Session ашиглах шаардлагагүй үед шууд **unlock** хийнэ  
 *   -> Ингэснээр зэрэг request-ууд саадгүй ажиллана  
 *
 * -------------------------------------------------------------
 * Зөвхөн хэл солих route (/language/...) нь session руу бичдэг.
 * -------------------------------------------------------------
 * Тиймээс зөвхөн `/language/...` URL нь session-г нээлттэй үлдээнэ.
 *
 * Бусад бүх веб хуудсууд session-г унших л шаардлагатай байдаг -> UNLOCK.
 *
 * -------------------------------------------------------------
 * Middleware процессын алхам
 * -------------------------------------------------------------
 *   1) Session нэрийг `raptor` болгоно
 *   2) Session идэвхгүй бол:
 *        - Cookie-г 30 хоног хүчинтэйгээр тохируулна  
 *        - `session_start()` хийнэ
 *   3) Session идэвхтэй бол:
 *        - Хэрэв URL нь `/language/...` биш бол -> `session_write_close()`
 *   4) Request-г дараагийн middleware/controller руу дамжуулна
 *
 * -------------------------------------------------------------
 * Хөгжүүлэгчид зориулав
 * -------------------------------------------------------------
 * * Web layer-д энэ middleware заавал хэрэгтэй  
 * * Dashboard layer-д өөр өөр SessionMiddleware ашигладаг  
 *   (Учир нь Dashboard нь session дээр их бичлэг хийдэг)  
 *
 * * Хэрэв хэл солих route-ийг өөр гэж тодорхойлж байгаа бол
 *   `/language/` нөхцлийг өөрчлөхөд хангалттай.
 *
 * @package Web
 */
class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        \session_name('raptor');
        
        if (\session_status() != \PHP_SESSION_ACTIVE) {
            $lifetime = \time() + 30 * 24 * 60 * 60; // 30 хоног
            \session_set_cookie_params($lifetime);
            \session_start();
        }
        
        if (\session_status() == \PHP_SESSION_ACTIVE) {
            $path = \rawurldecode($request->getUri()->getPath());

            // Root path-тай харьцуулах -> яг хийх request-ын path-ийг тодорхойлох
            if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
                $path = \substr($path, $lngth);
                $path = '/' . \ltrim($path, '/');
            }

            // Хэрэв хэл солих route биш бол session write-ийг хаах
            if (!\str_starts_with($path, '/language/')) {
                // Session lock-ийг хамгийн эрт тайлж өгөх нь хурд сайжруулна
                \session_write_close();
            }
        }
        
        return $handler->handle($request);
    }
}
