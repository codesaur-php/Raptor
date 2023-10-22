<?php

namespace Raptor\Contents;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;

use Raptor\Authentication\UserInterface;

class SettingsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $level = \ob_get_level();
            if (\ob_start()) {
                $alias = \getenv('CODESAUR_ORGANIZATION_ALIAS', true);
                $user = $request->getAttribute('user');
                if ($user instanceof UserInterface) {
                    $alias = $user->getOrganization()['alias'];
                }
                $localization = $request->getAttribute('localization');
                $request->getAttribute('indo')->handle(new InternalRequest(
                    'INTERNAL',
                    '/execute',
                    [
                        'query' =>
                            'SELECT p.keywords, p.email, p.phone, p.favico, p.shortcut_icon, p.apple_touch_icon, p.config, ' .
                            'c.title, c.logo, c.description, c.urgent, c.contact, c.address, c.copyright ' .
                            'FROM raptor_settings as p INNER JOIN raptor_settings_content as c ON p.id=c.parent_id ' .
                            'WHERE p.is_active=1 AND p.alias=:alias AND c.code=:code ' .
                            'ORDER BY p.updated_at desc LIMIT 1',
                        'bind' => [
                            ':alias' => ['var' => $alias ?: 'system'],
                            ':code' => ['var' => empty($localization['code']) ? 'en' : $localization['code']]
                        ]
                    ]   
                ));
                $response = \json_decode(\ob_get_contents(), true)
                    ?? ['error' => __CLASS__ . ': Error decoding Indoraptor response!'];
                if (isset($response['error']['code'])
                    && isset($response['error']['message'])
                ) {
                    throw new \Exception($response['error']['message'], $response['error']['code']);
                }
                $settings = \reset($response);
                if (!empty($settings['config'])) {
                    $settings['config'] =  \json_decode($settings['config'], true)
                        ?? ['error' => __CLASS__ . ': Error decoding config!'];
                } else {
                    $settings['config'] = [];
                }
                
                \ob_end_clean();
            }
        } catch (\Throwable $e) {
            if (isset($level)
                && \ob_get_level() > $level
            ) {
                \ob_end_clean();
            }
            
            if (\defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                \error_log($e->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('settings', $settings ?? []));
    }
}
