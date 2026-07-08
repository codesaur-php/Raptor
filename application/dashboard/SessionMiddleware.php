<?php

namespace Dashboard;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SessionMiddleware
 *
 * Session удирдлагын middleware. Dashboard болон Web app хоёуланд ашиглагдана.
 *
 * Session нээж, write-lock-ийг аль болох эрт суллана.
 * PHP session file lock нь concurrent request-уудыг бөглөдөг тул
 * session-д бичдэггүй route дээр session_write_close() дуудаж
 * concurrency сайжруулна.
 *
 * Constructor-аар needsWrite closure авна:
 *  - Dashboard: fn($path, $method) => str_contains($path, '/login')
 *  - Web: fn($path, $method) => str_starts_with($path, '/language/') || ...
 *
 * Closure null бол бүх route дээр session_write_close() дуудна.
 */
class SessionMiddleware implements MiddlewareInterface
{
    private ?\Closure $needsWrite;

    /**
     * @param \Closure|null $needsWrite fn(string $path, string $method): bool
     *        Session write шаардлагатай route-уудыг тодорхойлох callback.
     */
    public function __construct(?\Closure $needsWrite = null)
    {
        $this->needsWrite = $needsWrite;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        \session_name('raptor');

        if (\session_status() != \PHP_SESSION_ACTIVE) {
            // Session cookie-ийн тохиргоо.
            //
            // lifetime = 30 хоног (2592000 сек): зөвхөн client талын cookie-д
            // үйлчилнэ; server талын session файлын цэвэрлэгээ
            // (session.gc_maxlifetime) нь host php.ini-аар удирдагдсаар байна.
            // Энэ нь Раптор фреймворкийг олон серверийн орчинд нэмэлт тохиргоогүйгээр
            // ажиллуулж, админы нэвтрэлт browser хаагдсан ч хадгалагдах practical
            // default-ыг хангадаг.
            //
            // Аюулгүй байдлын flag-ууд (host php.ini-д найдалгүй ил тод тохируулна):
            //   httponly = true  -> JS document.cookie-ээр уншигдахгүй (XSS-ээс session хамгаална)
            //   secure   = HTTPS үед л true -> HTTP dev орчинд cookie ажиллана, HTTPS
            //              prod дээр зөвхөн шифрлэгдсэн холболтоор дамжина (аль ч серверт зөв)
            //   samesite = 'Lax' -> cross-site POST-оор cookie явахгүй (CSRF-ийг сааруулна)
            //
            // Developer session-ийн тохиргоог бүхэлд нь PHP-ийн өөрийн тохиргоо
            // (php.ini / .user.ini)-д даатгахыг хүсвэл доорх `session_set_cookie_params(...)`
            // дуудлагыг устгаад php.ini-д тохируулна (docs/mn/SESSION-LIFETIME.md-г үзээрэй).
            $server = $request->getServerParams();
            $isHttps = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
                || (($server['SERVER_PORT'] ?? null) == 443)
                || (($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            \session_set_cookie_params([
                'lifetime' => 2592000,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $isHttps,
                'samesite' => 'Lax',
            ]);

            // Session идэвхгүй (эхлээгүй) байгаа тул эхлүүлнэ
            \session_start();
        }

        if (\session_status() == \PHP_SESSION_ACTIVE) {
            $path = \rawurldecode($request->getUri()->getPath());
            $scriptRoot = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($len = \strlen($scriptRoot)) > 1) {
                $path = \substr($path, $len);
                $path = '/' . \ltrim($path, '/');
            }

            $writable = $this->needsWrite
                ? ($this->needsWrite)($path, $request->getMethod())
                : false;

            if (!$writable) {
                \session_write_close();
            }
        }

        return $handler->handle($request);
    }
}
