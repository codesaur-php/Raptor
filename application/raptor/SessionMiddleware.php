<?php

namespace Raptor;

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
            $lifetime = \time() + 30 * 24 * 60 * 60; // 30 хоног
            \session_set_cookie_params($lifetime);
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
