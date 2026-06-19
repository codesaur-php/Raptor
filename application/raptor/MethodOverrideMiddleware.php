<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class MethodOverrideMiddleware
 *
 * HTTP method override (verb tunneling) middleware.
 *
 * Олон shared hosting (cPanel/LiteSpeed, mod_security WAF) нь PUT/PATCH/DELETE
 * verb-ийг PHP-д хүрэхээс өмнө server түвшинд 403 Forbidden-оор блоклодог.
 * Үүний улмаас dashboard-ийн засвар/устгал (жишээ: news update) заримдаа
 * "HTTP [403]: Forbidden" буцаадаг.
 *
 * Шийдэл: клиент тал mutating хүсэлтийг POST-оор илгээж (server үргэлж
 * зөвшөөрдөг), жинхэнэ method-оо X-HTTP-Method-Override header-аар дамжуулна.
 * Энэ middleware тэр header-ийг уншиж, route matching хийгдэхээс өмнө request-ийн
 * method-ийг жинхэнэ утгаар нь сэргээнэ.
 *
 * Аюулгүй байдал:
 *  - Зөвхөн POST хүсэлтийг override хийнэ.
 *  - Зөвхөн PUT/PATCH/DELETE руу override зөвшөөрнө (GET/HEAD/OPTIONS руу
 *    биш - тэгэхгүй бол CsrfMiddleware-ийн safe-method bypass-аар CSRF
 *    шалгалтыг тойрох эрсдэлтэй). Override хийгдсэн PUT/PATCH/DELETE нь
 *    CsrfMiddleware-ийн шалгалтад хэвээр орно.
 *
 * Application::handle() дотор route matching нь $request->getMethod()-ийг
 * уншдаг тул энэ middleware-ийг global middleware-ээр (matching-аас өмнө)
 * залгахад л хангалттай.
 */
class MethodOverrideMiddleware implements MiddlewareInterface
{
    /** Override зөвшөөрөгдсөн method-ууд (mutating verb-үүд). */
    private const ALLOWED = ['PUT', 'PATCH', 'DELETE'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $override = \strtoupper(\trim($request->getHeaderLine('X-HTTP-Method-Override')));
            if ($override !== '' && \in_array($override, self::ALLOWED, true)) {
                // Verb-ийг сэргээгээд directive header-ийг арилгана (consume-after-use):
                // method аль хэдийн сэргэсэн тул header-ийн ажил дууссан.
                $request = $request->withMethod($override)
                    ->withoutHeader('X-HTTP-Method-Override');
            }
        }

        return $handler->handle($request);
    }
}
