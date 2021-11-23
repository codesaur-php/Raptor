<?php

namespace Raptor\Authentication;

use Throwable;
use Exception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

class LocalizationMiddleware implements MiddlewareInterface
{
    function request($indo, string $method, string $pattern, $payload = array())
    {
        try {
            ob_start();
            $indo->handle(new InternalRequest($method, $pattern, $payload));
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
        }
        
        return $response;
    }
    
    function retrieveLanguage(ServerRequestInterface $request): array
    {
        try {
            return $this->request($request->getAttribute('indo'), 'GET', '/language');
        } catch (Exception $e) {            
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }            
            return array('en' => 'English');
        }
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);
        $sess_lang_key = __NAMESPACE__ . '\\language\\code';
        if (isset($_SESSION[$sess_lang_key])
                && isset($language[$_SESSION[$sess_lang_key]])) {
            $code = $_SESSION[$sess_lang_key];
        } else {
            $code = key($language);
        }
        
        $text = array();
        try {
            $translations = $this->request(
                    $request->getAttribute('indo'), 'POST', '/translation/retrieve',
                    array('code' => $code, 'table' => ['dashboard', 'default', 'user']));
            foreach ($translations as $translation) {
                $text += $translation;
            }
        } catch (Exception $e) {
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('localization',
                array('language' => $language, 'code' => $code, 'text' => $text)));
    }
}
