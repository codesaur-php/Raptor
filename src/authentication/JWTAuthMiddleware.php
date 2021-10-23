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
        ob_start();
        $sess_jwt_key = 'indo/jwt' . $request->getAttribute('pipe', '');
        try {
            if (empty($_SESSION[$sess_jwt_key])) {
                throw new Exception('There is no JWT!');
            }            
            $request->getAttribute('indo')->handle(
                    new InternalRequest('POST', '/auth/jwt', array('jwt' => $_SESSION[$sess_jwt_key])));
        } catch (Throwable $th) {
            if (isset($_SESSION[$sess_jwt_key])
                    && session_status() == PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION[$sess_jwt_key]);
            }            
            $error = array('code' => $th->getCode(), 'message' => $th->getMessage());
            echo json_encode(array('error' => $error));
        }
        $response = json_decode(ob_get_contents(), true);
        ob_end_clean();

        
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
        $script_path = dirname($request->getServerParams()['SCRIPT_NAME']);
        $strip_path = (strlen($script_path) > 1 ? $script_path : '') . $request->getAttribute('pipe', '');
        $target_path = $strip_path != '' ? str_replace($strip_path, '', $uri_path) : $uri_path;
        $parts = explode('/', $target_path);
        if (($parts[1] ?? '') != 'login') {
            $loginUri = (string)$request->getUri()->withPath("$strip_path/login");
            header("Location: $loginUri", false, 302);
            exit;
        }
        
        return $handler->handle($request);
    }
}
