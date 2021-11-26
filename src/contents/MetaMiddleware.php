<?php

namespace Raptor\Contents;

use Throwable;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

class MetaMiddleware implements MiddlewareInterface
{   
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            ob_start();
            $request->getAttribute('indo')->handle(new InternalRequest('INTERNAL', '/meta'));
            $meta = json_decode(ob_get_contents(), true);
            ob_end_clean();
            
            if (isset($meta['error']['code'])
                    && isset($meta['error']['message'])
            ) {
                throw new Exception($meta['error']['message'], $meta['error']['code']);
            }
            
            return $handler->handle($request->withAttribute('meta', $meta));
        } catch (Throwable $th) {
            ob_end_clean();
            
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($th->getMessage());
            }
            
            return $handler->handle($request);
        }
    }
}
