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
    public function retrieveIndoUser(ServerRequestInterface $request, $jwt): User
    {
        try {
            ob_start();
            $request->getAttribute('indo')->handle(
                    new InternalRequest('POST', '/auth/jwt', array('jwt' => $jwt)));
            $response = json_decode(ob_get_contents(), true);
            ob_end_clean();
        } catch (Throwable $th) {
            ob_end_clean();
            
            $response = array('error' => array('code' => $th->getCode(), 'message' => $th->getMessage()));
        }
        
        if (isset($response['error']['code'])
                && isset($response['error']['message'])
        ) {
            throw new Exception($response['error']['message'], $response['error']['code']);
        } elseif (empty($response['rbac'])
                || !is_array($response['rbac'])
                || empty($response['account']['id'])
                || !isset($response['organizations'][0]['id'])
        ) {
            throw new Exception('Invalid RBAC user information!');
        }
        
        return new User($jwt, $response['rbac'], $response['account'], $response['organizations']);
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
        try {
            if (empty($_SESSION[$sess_jwt_key])) {
                throw new Exception('There is no JWT!');
            }

            $user = $this->retrieveIndoUser($request, $_SESSION[$sess_jwt_key]);
        } catch (Exception $e) {
            if ($e->getCode() >= 5000
                    && defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }
            
            if (isset($_SESSION[$sess_jwt_key])
                    && session_status() == PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION[$sess_jwt_key]);
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
        
        return $handler->handle($request->withAttribute('user', $user));
    }
}
