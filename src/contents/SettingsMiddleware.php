<?php

namespace Raptor\Contents;

use Throwable;
use Exception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;
use Indoraptor\Record\SettingsModel;

use Raptor\Authentication\RBACUserInterface;

class SettingsMiddleware implements MiddlewareInterface
{   
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            ob_start();            
            $alias = getenv('CODESAUR_ORGANIZATION_ALIAS', true);
            $user = $request->getAttribute('user');
            if ($user instanceof RBACUserInterface) {
                $alias = $user->getOrganization()['alias'];
            }            
            $request->getAttribute('indo')->handle(new InternalRequest(
                    'INTERNAL', '/record?model=' . SettingsModel::class, array('alias' => $alias ?: 'system', 'is_active' => 1)));
            $settings = json_decode(ob_get_contents(), true);            
            if (isset($settings['error']['code'])
                    && isset($settings['error']['message'])
            ) {
                throw new Exception($settings['error']['message'], $settings['error']['code']);
            }
            if (!empty($settings['socials'])) {
                $settings['socials'] = json_decode($settings['socials'], true);
            }
            if (!empty($settings['options'])) {
                $settings['options'] = json_decode($settings['options'], true);
            }
            ob_end_clean();
        } catch (Throwable $th) {
            ob_end_clean();
            
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($th->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('meta', $settings ?? array()));
    }
}
