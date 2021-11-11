<?php

namespace Raptor\Authentication;

use Throwable;
use Exception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

use Raptor\Authentication\User;

class JWTAuthMiddleware implements MiddlewareInterface
{
    function retrieveUserFromIndo(ServerRequestInterface $request)
    {
        $sess_jwt_key = 'indo/jwt' . $request->getAttribute('pipe', '');
        try {
            if (empty($_SESSION[$sess_jwt_key])) {
                throw new Exception('There is no JWT!');
            }
            $indo_buffer = true;
            ob_start();
            $indo_request = new InternalRequest('POST', '/auth/jwt', array('jwt' => $_SESSION[$sess_jwt_key]));
            $request->getAttribute('indo')->handle($indo_request);
            $response = json_decode(ob_get_contents(), true);
            ob_end_clean();
            $indo_buffer = false;
        } catch (Throwable $th) {
            if ($indo_buffer) {
                ob_end_clean();
            }
            if (isset($_SESSION[$sess_jwt_key])
                    && session_status() == PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION[$sess_jwt_key]);
            }
            $response = array('error' => array('code' => $th->getCode(), 'message' => $th->getMessage()));
        }
        return $response;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resource = $this->retrieveUserFromIndo($request);
        if (is_array($resource['rbac'])
                && isset($resource['account']['id'])
                && isset($resource['organizations'][0]['id'])
        ) {
            return $handler->handle($request->withAttribute('user', new User($resource)));
        }
        
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
        if (($parts[1] ?? '') != 'login') {
            $loginUri = (string)$request->getUri()->withPath("$script_path/login");
            header("Location: $loginUri", false, 302);
            exit;
        }
        
        return $handler->handle($request);
    }
}
