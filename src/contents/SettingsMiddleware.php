<?php

namespace Raptor\Contents;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Indoraptor\InternalRequest;
use Indoraptor\Contents\SettingsModel;

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
                $payload = ['alias' => $alias ?: 'system', 'is_active' => 1];
                if (empty($localization['code'])) {
                   $payload['code'] = 'en';
               } else {
                   $payload['code'] = $localization['code'];
               }
                $request->getAttribute('indo')->handle(
                    new InternalRequest('INTERNAL', '/record?model=' . SettingsModel::class, $payload));
                $settings = \json_decode(\ob_get_contents(), true)
                    ?? ['error' => __CLASS__ . ': Error decoding Indoraptor response!'];
                if (isset($settings['error']['code'])
                    && isset($settings['error']['message'])
                ) {
                    throw new \Exception($settings['error']['message'], $settings['error']['code']);
                }
                if (!empty($settings['socials'])) {
                    $settings['socials'] = \json_decode($settings['socials'], true)
                        ?? ['error' => __CLASS__ . ': Error decoding Indoraptor response!'];
                }
                if (!empty($settings['options'])) {
                    $settings['options'] = \json_decode($settings['options'], true)
                        ?? ['error' => __CLASS__ . ': Error decoding Indoraptor response!'];
                }
                
                foreach ($settings['content'] as $key => $content) {
                    $settings[$key] = $content[$payload['code']] ?? null;
                }
                unset($settings['content']);
                
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
        
        return $handler->handle($request->withAttribute('meta', $settings ?? []));
    }
}
