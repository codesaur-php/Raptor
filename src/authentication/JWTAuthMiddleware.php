<?php

namespace Raptor\Authentication;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;
use Indoraptor\IndoApplication;

use Raptor\Authentication\User;

class JWTAuthMiddleware implements MiddlewareInterface
{
    protected function request(?IndoApplication $indo, string $method, string $pattern, array $payload = [])
    {
        try {
            if (\ob_start()) {
                $indo?->handle(new InternalRequest($method, $pattern, $payload));
                $response = \json_decode(\ob_get_contents(), true)
                    ?? throw new \Exception(__CLASS__ . ': Error decoding Indoraptor response!');
                \ob_end_clean();
            }
        } catch (\Throwable $th) {
            if (\ob_get_level()) {
                \ob_end_clean();
            }
            
            $response = ['error' => ['code' => $th->getCode(), 'message' => $th->getMessage()]];
        }
        
        if (isset($response['error']['code'])
            && isset($response['error']['message'])
        ) {
            throw new \Exception($response['error']['message'], $response['error']['code']);
        }
        
        return $response;
    }
    
    public function retrieveIndoUser(ServerRequestInterface $request, string $jwt): User
    {
        $response = $this->request(
            $request->getAttribute('indo'), 'POST', '/auth/jwt', ['jwt' => $jwt]);
        if (
            !\is_array($response['rbac'] ?? null)
            || !isset($response['account']['id'])
            || !isset($response['organizations'][0]['id'])
        ) {
            throw new \Exception('Invalid RBAC user information!');
        }
        
        return new User($jwt, $response['rbac'], $response['account'], $response['organizations']);
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
        try {
            if (empty($_SESSION[$sess_jwt_key])) {
                throw new \Exception('There is no JWT!');
            }
            $user = $this->retrieveIndoUser($request, $_SESSION[$sess_jwt_key]);
        } catch (\Throwable $th) {
            if ($th->getCode() >= 5000
                && \defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT
            ) {
                \error_log($th->getMessage());
            }
            
            if (isset($_SESSION[$sess_jwt_key])
                && \session_status() == \PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION[$sess_jwt_key]);
            }
 
            $uri_path = \rawurldecode($request->getUri()->getPath());
            $script_path = $request->getServerParams()['SCRIPT_TARGET_PATH'] ?? null;
            if (!isset($script_path)) {
                $script_path = \dirname($request->getServerParams()['SCRIPT_NAME']);
                if ($script_path == '\\' || $script_path == '/') {
                    $script_path = '';
                }
            }
            if (!empty($script_path)) {
                $uri_path = \substr($uri_path, \strlen($script_path));
            }
            if (empty($uri_path)) {
                $uri_path = '/';
            }
            $parts = \explode('/', $uri_path);
            if ($parts[1] != 'login') {
                $loginUri = (string) $request->getUri()->withPath("$script_path/login");
                \header("Location: $loginUri", false, 302);
                exit;
            }
            
            return $handler->handle($request);
        }
        
        return $handler->handle($request->withAttribute('user', $user));
    }
}
