<?php

namespace Raptor\Authentication;

use Throwable;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

class LocalizationMiddleware implements MiddlewareInterface
{
    function request($indo, string $method, string $pattern, $payload = array())
    {
        ob_start();
        try {
            $indo->handle(new InternalRequest($method, $pattern, $payload));
        } catch (Throwable $th) {
            $error = array('code' => $th->getCode(), 'message' => $th->getMessage());
            echo json_encode(array('error' => $error));
        }
        $response = json_decode(ob_get_contents(), true);
        ob_end_clean();

        return $response;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $indo = $request->getAttribute('indo');
        
        $languages = $this->request($indo, 'GET', '/language');
        if (isset($languages['error'])) {
            $languages = array('en' => 'English');
        }
        $sess_lang_key = 'language/code' .  $request->getAttribute('pipe', '');
        if (isset($_SESSION[$sess_lang_key])
                && isset($languages[$_SESSION[$sess_lang_key]])) {
            $code = $_SESSION[$sess_lang_key];
        } else {
            $code = key($languages);
        }
        
        $translations = $this->request($indo, 'POST', '/translation/retrieve',
                array('code' => $code, 'table' => ['dashboard', 'default', 'user']));
        $localization = array('language' => $languages, 'code' => $code, 'text' => array());
        if (!isset($translations['error']['code'])
                && !isset($translations['error']['message'])) {
            foreach ($translations as $translation) {
                $localization['text'] += $translation;
            }
        }
        
        return $handler->handle($request->withAttribute('localization', $localization));
    }
}
