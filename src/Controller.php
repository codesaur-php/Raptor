<?php

namespace Raptor;

use Throwable;

use Twig\TwigFilter;

use codesaur\Globals\Server;
use codesaur\Router\RouterInterface;
use codesaur\Template\TwigTemplate;
use codesaur\RBAC\Accounts;

use Indoraptor\InternalRequest;

use Raptor\Authentication\RBACUserInterface;

class Controller extends \codesaur\Http\Application\Controller
{
    public function indo(string $pattern, array $payload = array(), string $method = 'INTERNAL', bool $assoc = true)
    {
        ob_start();
        try {
            $jwt = $_SESSION[$this->getSessionJWTIndex()] ?? null;
            $indo_request = new InternalRequest($method, $pattern, $payload, $jwt);
            $this->getAttribute('indo')->handle($indo_request);
        } catch (Throwable $th) {
            $error = array('code' => $th->getCode(), 'message' => $th->getMessage());
            echo json_encode(array('error' => $error));
        }
        $response = json_decode(ob_get_contents(), $assoc);
        ob_end_clean();
        
        return $response;
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
    
    final public function generateLink(string $routeName, array $params = [], $is_absolute = false, $default = 'javascript:;'): string
    {
        try {
            $route_path = $this->getRouter()->generate($routeName, $params);
            $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
            if (strlen($script_path) <= 1) {
                $script_path = '';
            }
            $app_path = $this->getAttribute('pipe', '');
            $pattern = $script_path . $app_path . $route_path;
            if (!$is_absolute) {
                return $pattern;
            }
            return $this->getRequest()->getUri()->withPath($pattern);
        } catch (Throwable $th) {
            if ($this->isDevelopment()) {
                error_log($th->getMessage());
            }
            return $default;
        }
    }
    
    public function redirectTo(string $routeName, array $params = [])
    {
        $link = $this->generateLink($routeName, $params);
        header("Location: $link", false, 302);
        exit;
    }

    final public function getLanguageCode()
    {
        return $this->getAttribute('localization')['code'] ?? 'en';
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
    
    public function language(string $code)
    {
        $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        if (strlen($script_path) <= 1) {
            $script_path = '';
        }                
        $app_path = $this->getAttribute('pipe', '');
        $pattern = $script_path . $app_path;
        $home = (string)$this->getRequest()->getUri()->withPath($pattern);
        $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
        $location = strpos($referer, $home) !== false ? $referer : $home;        
        $language = $this->getAttribute('localization')['language'];
        if (isset($language[$code])) {
            $_SESSION[$this->getSessionLangCodeIndex()] = $code;
            
            if ($this->isUserAuthorized()) {
                $account_id = $this->getUser()->getAccount()['id'];
                $record = array(
                    'record' => array('code' => $code),
                    'condition' => array('WHERE' => "id=$account_id")
                );
                $this->indoput('/record?model=' . Accounts::class, $record);
            }
        }
        
        header("Location: $location", false, 302);
        exit;
    }
    
    public function twigContent(string $template, array $vars = [])
    {
        $twigTemplate = new TwigTemplate($template, $vars);
        $twigTemplate->set('localization', $this->getAttribute('localization'));
        $twigTemplate->addFilter(new TwigFilter('text', function ($key): string
        {
            return $this->text($key);
        }));
        $twigTemplate->addFilter(new TwigFilter('link', function ($routeName, $params = [], $is_absolute = false): string
        {
            return $this->generateLink($routeName, $params, $is_absolute);
        }));
        return $twigTemplate;
    }
    
    public function indolog($message, array $context, $table = null, $level = null, $created_by = null)
    {
        if (empty($table)) {
            $table = $this->getQueryParam('logger') ?? 'default';
        }
        
        $context['server_request'] = array(
            'code' => $this->getLanguageCode(),
            'method' => $this->getRequest()->getMethod(),
            'uri' => (string)$this->getRequest()->getUri(),
            'remote_addr' => (new Server())->getRemoteAddr()
        );
        
        $payload = array(
            'table' => $table,
            'message' => $message,
            'context' => json_encode($context)
        );
        
        try {
            if (isset($created_by)) {
                $payload['created_by'] = $created_by;
            } elseif ($this->isUserAuthorized()) {
                $payload['created_by'] = $this->getUser()->getAccount()['id'];
            }
        } catch (Throwable $th) {
            if ($this->isDevelopment()) {
                error_log($th->getMessage());
            }
        }
        
        if (!empty($level)) {
            $payload['level'] = $level;
        }
        
        $this->indo('/log', $payload);
    }
    
    final public function getSessionJWTIndex()
    {
        return 'indo/jwt' . $this->getAttribute('pipe', '');
    }
    
    final public function getSessionLangCodeIndex()
    {
        return 'language/code' . $this->getAttribute('pipe', '');
    }
}
