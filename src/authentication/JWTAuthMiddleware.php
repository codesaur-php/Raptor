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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
        try {
            if (empty($_SESSION[$sess_jwt_key])) {
                throw new Exception('There is no JWT!');
            }
            
            $jwt = $_SESSION[$sess_jwt_key];
            $indo_buffer = true;
            ob_start();
            $indo_request = new InternalRequest('POST', '/auth/jwt', array('jwt' => $jwt));
            $request->getAttribute('indo')->handle($indo_request);
            $response = json_decode(ob_get_contents(), true);
            ob_end_clean();
            $indo_buffer = false;
            
            if (empty($response['rbac'])
                    || !is_array($response['rbac'])
                    || empty($response['account']['id'])
                    || !isset($response['organizations'][0]['id'])
            ) {
                throw new Exception('Invalid RBAC user information!');
            }
            
            return $handler->handle($request->withAttribute('user',
                    new User($jwt, $response['rbac'], $response['account'], $response['organizations'])));
        } catch (Throwable $th) {
            if ($indo_buffer ?? false) {
                ob_end_clean();
            }
            
            if (isset($_SESSION[$sess_jwt_key])
                    && session_status() == PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION[$sess_jwt_key]);
            }
            
            if ($th->getCode() >= 5000 && defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT) {
                error_log($th->getMessage());
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
            if ($parts[1] != 'login') {
                $loginUri = (string)$request->getUri()->withPath("$script_path/login");
                header("Location: $loginUri", false, 302);
                exit;
            }

            return $handler->handle($request);
        }
    }
}
