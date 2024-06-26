<?php

namespace Raptor;

use Twig\TwigFilter;
use Fig\Http\Message\StatusCodeInterface;

use codesaur\Router\RouterInterface;
use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

use Indoraptor\Internal\InternalRequest;

use Raptor\Authentication\UserInterface;

class Controller extends \codesaur\Http\Application\Controller
{
    public final function getRouter(): ?RouterInterface
    {
        return $this->getAttribute('router');
    }
    
    public final function getUser(): ?UserInterface
    {
        return $this->getAttribute('user');
    }
    
    public final function isUser(string $role): bool
    {
        return $this->getUser()?->is($role) ?? false;
    }
    
    public final function isUserAuthorized(): bool
    {
        return $this->getUser() instanceof UserInterface;
    }
    
    public final function isUserCan(string $permission): bool
    {
        return $this->getUser()?->can($permission) ?? false;
    }
    
    protected final function getScriptPath(): string
    {
        $script_link = \dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        if ($script_link == '\\' || $script_link == '/' || $script_link == '.') {
            $script_link = '';
        }
        return $script_link;
    }
    
    protected final function getTargetPath(): string
    {
        return $this->getRequest()->getServerParams()['SCRIPT_TARGET_PATH'] ?? $this->getScriptPath();
    }
    
    protected final function getDocumentRoot(): string
    {
        return \dirname($this->getRequest()->getServerParams()['SCRIPT_FILENAME']);
    }

    protected final function getDocumentPath(string $filePath): string
    {
        return $this->getDocumentRoot() . $filePath;
    }
    
    public final function generateLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = 'javascript:;'): string
    {
        try {
            $route_path = $this->getRouter()->generate($routeName, $params);
            $pattern = $this->getTargetPath() . $route_path;
            if (!$is_absolute) {
                return $pattern;
            }
            return (string) $this->getRequest()->getUri()->withPath($pattern);
        } catch (\Throwable $e) {
            $this->errorLog($e);

            return $default;
        }
    }

    public final function getLanguageCode(): string
    {
        return $this->getAttribute('localization')['code'] ?? '';
    }
    
    public final function getLanguages(): array
    {
        return $this->getAttribute('localization')['language'] ?? [];
    }

    public final function text($key): string
    {
        if (isset($this->getAttribute('localization')['text'][$key])) {
            return $this->getAttribute('localization')['text'][$key];
        }

        if ($this->isDevelopment()) {
            \error_log("TEXT NOT FOUND: $key");
        }

        return '{' . $key . '}';
    }
    
    public function twigTemplate(string $template, array $vars = []): TwigTemplate
    {
        $request_path = $this->getRequest()->getUri()->getPath();
        
        $twig = new TwigTemplate($template, $vars);
        $twig->set('user', $this->getUser());
        $twig->set('index', $this->getScriptPath());
        $twig->set('request', \rawurldecode($request_path));
        $twig->set('localization', $this->getAttribute('localization'));

        $twig->addFilter(new TwigFilter('text', function (string $key): string
        {
            return $this->text($key);
        }));

        $twig->addFilter(new TwigFilter('link', function (string $routeName, array $params = [], bool $is_absolute = false): string
        {
            return $this->generateLink($routeName, $params, $is_absolute);
        }));
        
        return $twig;
    }
    
    public function respondJSON(array $response, int|string $code = 0): void
    {
        if (!\headers_sent()) {
            if (!empty($code)
                && \is_int($code)
                && $code != StatusCodeInterface::STATUS_OK
                && \defined(ReasonPrhase::class . "::STATUS_$code")
            ) {
                \http_response_code($code);
            }
            \header('Content-Type: application/json');
        }
        
        echo \json_encode($response) ?: '{}';
    }
    
    public function redirectTo(string $routeName, array $params = [])
    {
        $link = $this->generateLink($routeName, $params);
        \header("Location: $link", false, 302);
        exit;
    }
    
    public function indo(string $pattern, array $payload = [], string $method = 'INTERNAL')
    {
        try {
            $level = \ob_get_level();
            if (\ob_start()) {
                $jwt = $this->isUserAuthorized() ? $this->getUser()->getToken() : null;
                $request = new InternalRequest($method, $pattern, $payload, $jwt);
                $this->getAttribute('indo')->handle($request);
                $response = \json_decode(\ob_get_contents(), true)
                    ?? throw new \Exception(__CLASS__ . ': Error decoding Indoraptor response!');
                \ob_end_clean();
            }
        } catch (\Throwable $e) { 
            if (isset($level)
                && \ob_get_level() > $level
            ) {
                \ob_end_clean();
            }
            
            $response = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        }
        
        if (isset($response['error']['code'])
            && isset($response['error']['message'])
        ) {
            $error_code = $response['error']['code'];
            throw new \Exception($response['error']['message'], \is_int($error_code) ? $error_code : 0);
        }
        
        return $response;
    }
    
    public final function indoget(string $pattern, $payload = [])
    {
        return $this->indo($pattern, $payload, 'GET');
    }
    
    public final function indopost(string $pattern, $payload = [])
    {
        return $this->indo($pattern, $payload, 'POST');
    }

    public final function indoput(string $pattern, $payload = [])
    {
        return $this->indo($pattern, $payload, 'PUT');
    }
    
    public final function indodelete(string $pattern, $payload = [])
    {
        return $this->indo($pattern, $payload, 'DELETE');
    }
    
    public final function indolog(string $table, string $level, string $message, array $context = [], ?int $created_by = null)
    {
        try {
            if (!isset($context['server_request'])) {
                $context['server_request'] = [
                    'code' => $this->getLanguageCode(),
                    'method' => $this->getRequest()->getMethod(),
                    'uri' => (string) $this->getRequest()->getUri(),
                    'remote_addr' => $this->getRemoteAddr()
                ];
            }

            $payload = [
                'table' => $table,
                'level' => $level,
                'message' => $message,
                'context' => \json_encode($context)
                ?: throw new \Exception(__CLASS__ . ': Error encoding log context')
            ];
            
            if (isset($created_by)) {
                $payload['created_by'] = $created_by;
            }
            
            return $this->indo('/log', $payload);
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            return [];
        }
    }
    
    protected final function errorLog(\Throwable $e)
    {
        if (!$this->isDevelopment()) {
            return;
        }
        
        \error_log($e->getMessage());
    }

    protected function getRemoteAddr(): string
    {
        $server = $this->getRequest()->getServerParams();
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            if (!empty($server['HTTP_CLIENT_IP'])
                && $this->isValidIP($server['HTTP_CLIENT_IP'])
            ) {
                return $server['HTTP_CLIENT_IP'];
            }
            foreach (\explode(',', $server['HTTP_X_FORWARDED_FOR']) as $ip) {
                if ($this->isValidIP(\trim($ip))) {
                    return $ip;
                }
            }
        }

        if (!empty($server['HTTP_X_FORWARDED'])
            && $this->isValidIP($server['HTTP_X_FORWARDED'])
        ) {
            return $server['HTTP_X_FORWARDED'];
        } elseif (!empty($server['HTTP_X_CLUSTER_CLIENT_IP'])
            && $this->isValidIP($server['HTTP_X_CLUSTER_CLIENT_IP'])
        ) {
            return $server['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (!empty($server['HTTP_FORWARDED_FOR'])
            && $this->isValidIP($server['HTTP_FORWARDED_FOR'])
        ) {
            return $server['HTTP_FORWARDED_FOR'];
        } elseif (!empty($server['HTTP_FORWARDED'])
            && $this->isValidIP($server['HTTP_FORWARDED'])
        ) {
            return $server['HTTP_FORWARDED'];
        }

        return $server['REMOTE_ADDR'] ?? '';
    }
    
    private function isValidIP(string $ip): bool
    {
        $real = \ip2long($ip);
        if (empty($ip) || $real == -1 || $real === false) {
            return false;
        }

        $private_ips = [
            ['0.0.0.0', '2.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.2.0', '192.0.2.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['255.255.255.0', '255.255.255.255']
        ];
        foreach ($private_ips as $r) {
            $min = \ip2long($r[0]);
            $max = \ip2long($r[1]);
            if ($real >= $min && $real <= $max) {
                return false;
            }
        }

        return true;
    }
}
