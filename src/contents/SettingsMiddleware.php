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

use Raptor\Authentication\UserInterface;

class SettingsMiddleware implements MiddlewareInterface
{   
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            ob_start();
            $alias = getenv('CODESAUR_ORGANIZATION_ALIAS', true);
            $user = $request->getAttribute('user');
            if ($user instanceof UserInterface) {
                $alias = $user->getOrganization()['alias'];
            }
            $localization = $request->getAttribute('localization');
            $payload = array('alias' => $alias ?: 'system', 'is_active' => 1);
            if (!empty($localization['code'])) {
               $payload['code'] = 'mn';
           }
            $request->getAttribute('indo')->handle(
                new InternalRequest('INTERNAL', '/record?model=' . SettingsModel::class, $payload));
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
        } catch (Throwable $e) {
            ob_end_clean();
            
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('meta', $settings ?? array()));
    }
}
