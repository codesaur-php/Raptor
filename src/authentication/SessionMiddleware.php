<?php

namespace Raptor\Authentication;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        session_name('raptor');
        
        if (session_status() != PHP_SESSION_ACTIVE) {
            $lifetime = time() + 30 * 24 * 60 * 60;
            session_set_cookie_params($lifetime);
            session_start();
        }
        
        if (session_status() == PHP_SESSION_ACTIVE) {
            $uri_path = rawurldecode($request->getUri()->getPath());
            $script_path = $request->getServerParams()['SCRIPT_TARGET_PATH'] ?? null;
            if (!isset($script_path)) {
                $script_path = dirname($request->getServerParams()['SCRIPT_NAME']);
                if ($script_path == '\\' || $script_path == '/') {
                    $script_path = '';
                }
            }
            if (!empty($script_path)) {
                $uri_path = substr($uri_path, strlen($script_path));
            }
            if (empty($uri_path)) {
                $uri_path = '/';
            }
            $parts = explode('/', $uri_path);
            if ($parts[1] != 'login') {
                // Only login routes needs write access on $_SESSION,
                // otherwise we better write_close the session as soon as possible
                session_write_close();
            }
        }
        
        return $handler->handle($request);
    }
}
