<?php

namespace Raptor;

use Throwable;
use Exception;

use Twig\TwigFilter;
use Psr\Log\LogLevel;
use Fig\Http\Message\StatusCodeInterface;

use codesaur\Globals\Server;
use codesaur\Router\RouterInterface;
use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

use Indoraptor\InternalRequest;

use Raptor\Authentication\UserInterface;

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
    
    final public function getUser(): ?UserInterface
    {
        return $this->getAttribute('user');
    }
    
    final public function isUserAuthorized(): bool
    {
        return $this->getUser() instanceof UserInterface;
    }
    
    final public function isUserCan(string $permission): bool
    {
        return $this->isUserAuthorized() && $this->getUser()->can($permission);
    }
    
    final function getScriptPath(): string
    {
        $script_link = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        if ($script_link == '\\' || $script_link == '/' || $script_link == '.') {
            $script_link = '';
        }
        return $script_link;
    }
    
    final function getTargetPath(): string
    {
        return $this->getRequest()->getServerParams()['SCRIPT_TARGET_PATH'] ?? $this->getScriptPath();
    }
    
    final public function generateLink(string $routeName, array $params = [], $is_absolute = false, $default = 'javascript:;'): string
    {
        try {
            $route_path = $this->getRouter()->generate($routeName, $params);
            $pattern = $this->getTargetPath() . $route_path;
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
            error_log("TEXT NOT FOUND: $key");
        }

        return '{' . $key . '}';
    }
    
    final public function getStatusValues()
    {
        if ($this->getLanguageCode() == 'mn') {
            return array(1 => 'Идэвхтэй', 0 => 'Идэвхгүй');
        }
        else {
            return array(1 => 'Active', 0 => 'Inactive');
        }
    }
    
    public function twigTemplate(string $template, array $vars = [])
    {
        $twig = new TwigTemplate($template, $vars);            
        $twig->set('user', $this->getUser());
        $twig->set('localization', $this->getAttribute('localization'));
        $twig->set('request_path', rtrim($_SERVER['REQUEST_URI'], '/'));
        $twig->set('request_uri', (string)$this->getRequest()->getUri());
        $twig->addFilter(new TwigFilter('text', function ($key): string
        {
            return $this->text($key);
        }));
        $twig->addFilter(new TwigFilter('link',
            function ($routeName, $params = [], $is_absolute = false): string
        {
            return $this->generateLink($routeName, $params, $is_absolute);
        }));
        
        return $twig;
    }
    
    public function respondJSON(array $res, $code = null)
    {
        if (!headers_sent()) {
            if (!empty($code)) {
                if ($code != StatusCodeInterface::STATUS_OK) {
                    $status_code = "STATUS_$code";
                    $reasonPhraseClass = ReasonPrhase::class;
                    if (defined("$reasonPhraseClass::$status_code")) {
                        http_response_code($code);
                    }
                }
            }
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
            if (!isset($context['server_request'])) {
                $context['server_request'] = array(
                    'code' => $this->getLanguageCode(),
                    'method' => $this->getRequest()->getMethod(),
                    'uri' => (string)$this->getRequest()->getUri(),
                    'remote_addr' => (new Server())->getRemoteAddr()
                );
            }

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
    
    final public function indosafe(string $pattern, array $payload = array(), string $method = 'INTERNAL', bool $assoc = true)
    {
        try {
            return $this->indo($pattern, $payload, $method, $assoc);
        } catch (Exception $e) {
            $this->errorLog($e);
            
            return array();
        }
    }
    
    public function tryDeleteFile(string $filePath)
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
                
                $this->indolog('file', LogLevel::ALERT, "$filePath файлыг устгалаа");
            }
        } catch (Throwable $ex) {
            $this->errorLog($ex);
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
