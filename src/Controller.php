<?php

namespace Raptor;

use Throwable;
use Exception;

use Twig\TwigFilter;

use codesaur\Globals\Server;
use codesaur\Router\RouterInterface;
use codesaur\Template\TwigTemplate;
use codesaur\Template\IndexTemplate;

use Indoraptor\InternalRequest;

use Raptor\Authentication\RBACUserInterface;

class Controller extends \codesaur\Http\Application\Controller
{
    public function indo(string $pattern, array $payload = array(), string $method = 'INTERNAL', bool $assoc = true)
    {
        try {
            ob_start();
            $jwt = $this->isUserAuthorized() ? $this->getUser()->getToken() : null;
            $request = new InternalRequest($method, $pattern, $payload, $jwt);
            $this->getAttribute('indo')->handle($request);
            $response = json_decode(ob_get_contents(), $assoc);
            ob_end_clean();
        } catch (Throwable $e) {
            ob_end_clean();
        }

        if (isset($e)) {
            if ($e instanceof Exception) {
                throw $e;
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } elseif (isset($response['error']['code'])
                && isset($response['error']['message'])
        ) {
            throw new Exception($response['error']['message'], $response['error']['code']);
        }
        
        return $response;
    }
    
    final public function getRouter(): ?RouterInterface
    {
        return $this->getAttribute('router');
    }
    
    final public function getUser(): ?RBACUserInterface
    {
        return $this->getAttribute('user');
    }
    
    final public function isUserAuthorized(): bool
    {
        return $this->getUser() instanceof RBACUserInterface;
    }
    
    final public function isUserCan(string $permission): bool
    {
        return $this->isUserAuthorized() && $this->getUser()->can($permission);
    }
    
    final public function generateLink(string $routeName, array $params = [], $is_absolute = false, $default = 'javascript:;'): string
    {
        try {
            $route_path = $this->getRouter()->generate($routeName, $params);            
            $script_path = $this->getRequest()->getServerParams()['SCRIPT_TARGET_PATH'] ?? null;
            if (!isset($script_path)) {
                $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
                if ($script_path == '\\' || $script_path == '/') {
                    $script_path = '';
                }
            }
            $pattern = $script_path . $route_path;
            if (!$is_absolute) {
                return $pattern;
            }
            return (string)$this->getRequest()->getUri()->withPath($pattern);
        } catch (Throwable $e) {
            $this->errorLog($e);

            return $default;
        }
    }

    final public function getLanguageCode()
    {
        return $this->getAttribute('localization')['code'] ?? 'en';
    }
    
    final public function getLanguages()
    {
        return $this->getAttribute('localization')['language'] ?? ['en' => 'English'];
    }

    final public function text($key): string
    {
        if (isset($this->getAttribute('localization')['text'][$key])) {
            return $this->getAttribute('localization')['text'][$key];
        }

        if ($this->isDevelopment()) {
            error_log("UNTRANSLATED: $key");
        }

        return '{' . $key . '}';
    }
    
    public function twigTemplate(string $template_path, array $vars = [])
    {
        return $this->setTemplateGlobal(new TwigTemplate($template_path, $vars));
    }
    
    public function setTemplateGlobal(TwigTemplate &$template)
    {
        $template->set('user', $this->getUser());
        $template->set('localization', $this->getAttribute('localization'));
        $template->set('request_path', rtrim($_SERVER['REQUEST_URI'], '/'));
        $template->set('request_uri', (string)$this->getRequest()->getUri());
        $template->addFilter(new TwigFilter('text', function ($key): string
        {
            return $this->text($key);
        }));
        $template->addFilter(new TwigFilter('link', function ($routeName, $params = [], $is_absolute = false): string
        {
            return $this->generateLink($routeName, $params, $is_absolute);
        }));
        
        if ($template instanceof IndexTemplate) {
            $template->set('meta', $this->getAttribute('meta'));
        }
        
        return $template;
    }
    
    public function respondJSON(array $res)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        echo json_encode($res);
    }
    
    public function redirectTo(string $routeName, array $params = [])
    {
        $link = $this->generateLink($routeName, $params);
        header("Location: $link", false, 302);
        exit;
    }
    
    final public function indoget(string $pattern, $payload = [], bool $assoc = true)
    {
        return $this->indo($pattern, $payload, 'GET', $assoc);
    }
    
    final public function indopost(string $pattern, $payload = [], bool $assoc = true)
    {
        return $this->indo($pattern, $payload, 'POST', $assoc);
    }

    final public function indoput(string $pattern, $payload = [], bool $assoc = true)
    {
        return $this->indo($pattern, $payload, 'PUT', $assoc);
    }
    
    final public function indodelete(string $pattern, $payload = [], bool $assoc = true)
    {
        return $this->indo($pattern, $payload, 'DELETE', $assoc);
    }
    
    final public function indolog(string $table, string $level, $message, array $context = [], $created_by = null)
    {
        try {
            $context['server_request'] = array(
                'code' => $this->getLanguageCode(),
                'method' => $this->getRequest()->getMethod(),
                'uri' => (string)$this->getRequest()->getUri(),
                'remote_addr' => (new Server())->getRemoteAddr()
            );

            $payload = array(
                'table' => $table,
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context)
            );
            
            if (isset($created_by)) {
                $payload['created_by'] = $created_by;
            } elseif ($this->isUserAuthorized()) {
                $payload['created_by'] = $this->getUser()->getAccount()['id'];
            }
            
            $this->indo('/log', $payload);
        } catch (Throwable $e) {
            $this->errorLog($e);
        }
    }
    
    final public function indoSafe(string $pattern, array $payload = array(), string $method = 'INTERNAL', bool $assoc = true)
    {
        try {
            return $this->indo($pattern, $payload, $method, $assoc);
        } catch (Exception $e) {
            $this->errorLog($e);
            
            return false;
        }
    }
    
    final function errorLog(Throwable $e)
    {
        if (!$this->isDevelopment()) {
            return;
        }
        
        error_log($e->getMessage());
    }
}
