<?php

namespace Raptor\Authentication;

use Exception;
use Throwable;
use DateTime;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Account\ForgotModel;
use Indoraptor\Account\AccountErrorCode;
use Indoraptor\Account\OrganizationUserModel;

define('CODESAUR_PASSWORD_RESET_MINUTES', $_ENV['CODESAUR_PASSWORD_RESET_MINUTES'] ?? 10);

class LoginController extends \Raptor\Controller
{
    public function index()
    {
        $forgot_id = $this->getQueryParams()['forgot'] ?? false;
        if ($forgot_id) {
            return $this->forgotPassword($forgot_id);
        }
        
        if ($this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }
        
        $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
        $vars = array('meta' => $this->getAttribute('meta'));
        $vars += $this->indosafe('/lookup', array('table' => 'templates', 'condition' =>
            array('WHERE' => "c.code='$code' AND (p.keyword='tos' OR p.keyword='pp') AND p.is_active=1")));
        
        $this->twigTemplate(dirname(__FILE__) . '/login.html', $vars)->render();
    }
    
    public function entry()
    {
        try {
            $context = array('reason' => 'login');
            $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
            $payload = $this->getParsedBody();
            
            $context += array('payload' => $payload);            
            if (isset($context['payload']['password'])) {
                unset($context['payload']['password']);
            }

            if ($this->isUserAuthorized()) {
                throw new Exception($this->text('invalid-request'));
            }

            $account = $this->indopost('/auth/entry', $payload);
            $_SESSION[$sess_jwt_key] = $account['jwt'];
            $this->respondJSON(array('type' => 'success', 'message' => 'success', 'url' => $this->generateLink('home', [], true)));

            if (empty($account['code'])) {
                $this->postAccountLanguageCode($account['id'], $this->getLanguageCode());
            } elseif ($account['code'] != $this->getLanguageCode()
                && isset($this->getAttribute('localization')['language'][$account['code']])
            ) {
                $_SESSION[explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $account['code'];
            }
            
            $level = LogLevel::INFO;
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системд нэвтрэв.";
        } catch (Throwable $e) {
            if (isset($_SESSION[$sess_jwt_key])) {
                unset($_SESSION[$sess_jwt_key]);
            }
            $this->respondJSON(array('type' => 'danger', 'message' => $e->getMessage()));

            $this->errorLog($e);
            
            $level = LogLevel::ERROR;
            $message = $e->getMessage();
            $context += array('reason' => 'attempt', 'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('dashboard', $level, $message, $context, $account['id'] ?? null);
        }
    }

    public function logout()
    {
        $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
        if (isset($_SESSION[$sess_jwt_key])) {
            unset($_SESSION[$sess_jwt_key]);

            $account = $this->getUser()->getAccount();
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системээс гарлаа.";
            $context = array('reason' => 'logout', 'jwt' => $_SESSION[$sess_jwt_key]);
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);            
        }
        
        $this->redirectTo('home');
    }
    
    public function signup()
    {
        
        try {
            $context = array('reason' => 'request-new-account');
            
            $payload = $this->getParsedBody();
            $context += array('payload' => $payload);
            if (isset($payload['password'])) {
                $password = $payload['password'];
                unset($context['payload']['password']);
            } else {
                $password = '';
            }
            if (isset($payload['password_re'])) {
                $passwordRe = $payload['password_re'];
                unset($context['payload']['password_re']);
            } else {
                $passwordRe = '';
            }            
            if (empty($password) || $password != $passwordRe) {
                throw new Exception($this->text('invalid-request'));
            } else {
                unset($payload['password_re']);
            }

            $payload['password'] = password_hash($password, PASSWORD_BCRYPT);
            $payload['code'] = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            
            $lookup = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='{$payload['code']}' AND p.keyword='request-new-account' AND p.is_active=1")));
            if (empty($lookup['request-new-account'])) {
                throw new Exception($this->text('email-template-not-set'));
            }
            $content = $lookup['request-new-account'];
            
            $this->indopost('/account/signup', $payload);

            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('username', $payload['username']);
            $template->source($content['full'][$payload['code']]);
            $this->indo('/send/stmp/email', array(
                'name' => $payload['username'],
                'to' => $payload['email'],
                'code' => $payload['code'],
                'message' => $template->output(),
                'subject' => $content['title'][$payload['code']]
            ));
            $this->respondJSON(array('type' => 'success', 'message' => $this->text('to-complete-registration-check-email')));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['username']} нэртэй {$payload['email']} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ";
        } catch (Throwable $e) {
            switch ($e->getCode()) {
                case AccountErrorCode::INSERT_DUPLICATE_EMAIL: $message = "Бүртгэлтэй [{$payload['email']}] хаягаар шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав"; break;
                case AccountErrorCode::INSERT_DUPLICATE_USERNAME: $message = "Бүртгэлтэй {$payload['username']} хэрэглэгчийн нэрээр шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав"; break;
                case AccountErrorCode::INSERT_DUPLICATE_NEWBIE: $message = "Шинээр {$payload['username']} нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав"; break;
                case AccountErrorCode::INSERT_NEWBIE_FAILURE: $message = "Шинээр {$payload['username']} нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо"; break;
                default: $message = 'Шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүлэх үед алдаа гарч зогслоо. <p>' . $e->getMessage() . '</p>'; break;
            }            
            $this->respondJSON(array('type' => 'danger', 'message' => $message));
            
            $level = LogLevel::ERROR;
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function requestPassword()
    {
        try {
            $context = array('reason' => 'request-password');
            
            $payload = $this->getParsedBody();
            $payload['code'] = $this->getLanguageCode();
            $payload['login'] = $this->generateLink('login', [], true);
            
            $lookup = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='{$payload['code']}' AND p.keyword='forgotten-password-reset' AND p.is_active=1")));
            if (empty($lookup['forgotten-password-reset'])) {
                throw new Exception($this->text('email-template-not-set'));
            }
            $content = $lookup['forgotten-password-reset'];
            
            $forgot = $this->indopost('/account/forgot', $payload);
            $context += array('forgot' => $forgot);
            
            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('minutes', CODESAUR_PASSWORD_RESET_MINUTES);
            $template->set('link', "{$payload['login']}?forgot={$forgot['use_id']}");
            $template->source($content['full'][$payload['code']]);
            $receiver = $forgot['first_name'] . ' ' . $forgot['last_name'];
            $this->indo('/send/stmp/email', array(
                'name' => $receiver,
                'to' => $payload['email'],
                'code' => $payload['code'],
                'message' => $template->output(),
                'subject' => $content['title'][$payload['code']]                
            ));
            $this->respondJSON(array('type' => 'success', 'message' => $this->text('reset-email-sent')));

            $level = LogLevel::INFO;
            $message = "{$payload['email']} хаягтай хэрэглэгч  нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгүүллээ";
        } catch (Throwable $e) {
            switch ($e->getCode()) {
                case AccountErrorCode::ACCOUNT_NOT_FOUND: $message = "Бүртгэлгүй [{$payload['email']}] хаяг дээр нууц үг шинээр тааруулах хүсэлт илгээхийг оролдлоо. Татгалзав."; break;
                case AccountErrorCode::ACCOUNT_NOT_ACTIVE: $message = "Эрх нь нээгдээгүй хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээх оролдлого хийв. Татгалзав."; break;
                case AccountErrorCode::INSERT_FORGOT_FAILURE: $message = "Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо."; break;
                default: $message = 'Хэрэглэгч нууц үгээ шинэчлэх хүсэлт илгээх үед алдаа гарч зогслоо'; break;
            }            
            $this->respondJSON(array('type' => 'danger', 'message' => $message));

            $level = LogLevel::ERROR;
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function forgotPassword($use_id)
    {
        try {            
            $vars = array('use_id' => $use_id);            
            $context = array('reason' => 'forgot-password') + $vars;
            
            $forgot = $this->indo('/record?model=' . ForgotModel::class, array('use_id' => $use_id, 'is_active' => 1));
            $code = $forgot['code'];
            if ($code != $this->getLanguageCode()) {
                if (isset($this->getAttribute('localization')['language'][$code])) {
                    $_SESSION[explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $code;
                    $link = $this->generateLink('login') . "?forgot=$use_id";
                    header("Location: $link", false, 302);
                    exit;
                }
            }
            $context += array('forgot' => $forgot);
            
            $now_date = new DateTime();
            $then = new DateTime($forgot['created_at']);
            $diff = $then->diff($now_date);
            if ($diff->y > 0 || $diff->m > 0 || $diff->d > 0
                || $diff->h > 0 || $diff->i > (int)CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new Exception('Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв');
            }
            $vars += array('use_id' => $use_id, 'account' => $forgot['account']);
            
            $level = LogLevel::ALERT;
            $message = 'Нууц үгээ шинээр тааруулж эхэллээ.';           
        } catch (Throwable $e) {
            if ($e->getCode() == 404) {
                $notice = 'Хуурамч/устгагдсан/хэрэглэгдсэн мэдээлэл ашиглан нууц үг тааруулахыг оролдов';
            } else {
                $notice = $e->getMessage();
            }
            $vars += array('title' => $this->text('error'), 'notice' => $notice);

            $level = LogLevel::ERROR;
            $message = "Нууц үгээ шинээр тааруулж эхлэх үед алдаа гарч зогслоо. $notice";
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->twigTemplate(
                dirname(__FILE__) . '/login-reset-password.html', 
                $vars + array('meta' => $this->getAttribute('meta')))->render();
            
            $this->indolog('dashboard', $level, $message, $context, $forgot['account'] ?? null);
        }
    }
    
    public function setPassword()
    {        
        try {
            $context = array('reason' => 'reset-password');
            $parsedBody = $this->getParsedBody();
            $use_id = $parsedBody['use_id'];
            $vars = array('use_id' => $use_id);
            $context += array('payload' => $parsedBody) + $vars;            
            if (isset($parsedBody['password_new'])) {
                $password_new = $parsedBody['password_new'];
                unset($context['payload']['password_new']);
            } else {
                $password_new =  null;
            }
            if (isset($parsedBody['password_retype'])) {
                $password_retype = $parsedBody['password_retype'];
                unset($context['payload']['password_retype']);
            } else {
                $password_retype = null;
            }            
            $account_id = filter_var($parsedBody['account'], FILTER_VALIDATE_INT);
            if ($account_id === false) {
                throw new Exception('Хэрэглэгчийн дугаар заагдаагүй байна.<br/>' . $this->text('invalid-request-data'));
            }
            $vars += array('account' => $account_id);

            if (empty($use_id) || empty($account_id)
                    || !isset($password_new) || !isset($password_retype)
            ) {
                return $this->redirectTo('home');
            }
            
            if (empty($password_new) || $password_new != $password_retype) {
                throw new Exception('Шинэ нууц үгээ буруу бичсэн.<br/>' . $this->text('password-must-match'));
            }

            $payload = array(
                'use_id' => $use_id,
                'account' => $account_id,
                'password' => password_hash($password_new, PASSWORD_BCRYPT)
            );
            $context += array('account_id' => $account_id);
            
            $account = $this->indoput('/account/password', $payload);
            $vars += array('title' => $this->text('success'), 'notice' => $this->text('set-new-password-success'));

            $level = LogLevel::INFO;
            $message = 'Нууц үг шинээр тохируулав';
            $context += array('account' => $account);
        } catch (Throwable $e) {
            $vars['error'] = $e->getMessage();
            
            $level = LogLevel::ERROR;
            $message = 'Шинээр нууц үг тааруулах үед алдаа гарлаа';
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->twigTemplate(
                dirname(__FILE__) . '/login-reset-password.html', 
                $vars + array('meta' => $this->getAttribute('meta')))->render();
            
            $this->indolog('dashboard', $level, $message, $context, $account['id'] ?? null);
        }
    }
    
    public function selectOrganization(int $id)
    {
        if (!$this->isUserAuthorized() || $id == 0) {
            return $this->redirectTo('home');
        }
        
        $current_org_id = $this->getUser()->getOrganization()['id'];
        if ($id == $current_org_id) {
            return $this->redirectTo('home');
        }
        
        $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'] ?? null;
        try {
            $account_id = $this->getUser()->getAccount()['id'];
            $payload = array('account_id' => $account_id, 'organization_id' => $id);
            $this->indo('/record?model=' . OrganizationUserModel::class, $payload + array('is_active' => 1));
            
            $jwt_result = $this->indopost('/auth/organization', $payload);
            $_SESSION[__NAMESPACE__ . '\\indo\\jwt'] = $jwt_result['jwt'];
            $home = $this->generateLink('home');
            $location = strpos($referer, $home) !== false ? $referer : $home;

            $account = $this->getUser()->getAccount();
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} нэвтэрсэн байгууллага сонгов.";
            $context = array('reason' => 'login-to-organization', 'enter' => $id, 'leave' => $current_org_id, 'jwt' => $jwt_result['jwt']);
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            $location = $referer;
        } finally {
            header("Location: $location", false, 302);
            
            exit;
        }
    }
    
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $target_path = $this->getTargetPath();
        $home = (string)$this->getRequest()->getUri()->withPath($target_path);
        $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
        $location = strpos($referer, $home) !== false ? $referer : $home;        
        $language = $this->getAttribute('localization')['language'];
        if (isset($language[$code]) && $code != $from) {
            $_SESSION[explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $code;            
            if ($this->isUserAuthorized()) {
                try {
                    $account = $this->getUser()->getAccount();
                    $payload = array(
                        'record' => array('code' => $code),
                        'condition' => array('WHERE' => "id={$account['id']}")
                    );
                    $this->indoput('/record?model=' . Accounts::class, $payload);
                    
                    $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системд ажиллах хэлийг $from-с $code болгон өөрчиллөө.";
                    $context = array('reason' => 'change-language', 'code' => $code, 'from' => $from);
                    $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
                } catch (Exception $e) {
                    $this->errorLog($e);
                }
            }
        }
        
        header("Location: $location", false, 302);
        exit;
    }
    
    function postAccountLanguageCode(int $id, string $code)
    {
        try {
            return $this->indo('/record/update?model=' . Accounts::class,
                array('record' => array('code' => $code), 'condition' => array('WHERE' => "id=$id")));
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            return false;
        }
    }
}
