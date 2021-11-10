<?php

namespace Raptor\Authentication;

use Throwable;
use Exception;
use DateTime;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Account\AccountErrorCode;
use Indoraptor\Account\ForgotModel;
use Indoraptor\Account\OrganizationModel;
use Indoraptor\Account\OrganizationUserModel;

class LoginController extends \Raptor\Controller
{
    public function index()
    {
        $forgot_id = $this->getQueryParam('forgot');
        if ($forgot_id) {
            return $this->forgotPassword($forgot_id);
        }
        
        if ($this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }
        
        $code = $this->getLanguageCode();
        $vars = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
            array('WHERE' => "c.code='$code' AND (p.keyword='tos' OR p.keyword='pp') AND p.is_active=1")));
        $vars['organizations'] = $this->indo('/record/rows?model=' . OrganizationModel::class)['rows'] ?? [];

        $this->twigTemplate(dirname(__FILE__) . '/login.html', $vars)->render();
    }
        
    public function entry()
    {
        try {
            $sess_jwt_key = $this->getSessionJWTIndex();
            $payload = $this->getParsedBody();
            $username = $payload['username'];
            $password = $payload['password'];
            if ($this->isUserAuthorized()
                    || empty($username) || empty($password)
            ) {
                throw new Exception($this->text('invalid-request'));
            }

            $response = $this->indopost('/auth/entry',
                    array('username' => $username, 'password' => $password));
            if (!isset($response['account']['jwt'])) {
                throw new Exception($response['error']['message'] ?? $this->text('invalid-response!'));
            }
            
            $_SESSION[$sess_jwt_key] =  $response['account']['jwt'];
            $this->respondJSON(array('type' => 'success', 'message' => 'success', 'url' => $this->generateLink('home', [], true)));

            $log_message ="Хэрэглэгч {$response['account']['first_name']} " .
                    "{$response['account']['last_name']} системд нэвтрэв.";
            $log_context = array('reason' => 'login', 'account' => $username);
            
            if (empty($response['account']['code'])) {
                $this->indo('/record/update?model=' . Accounts::class,
                        array('record' => array('code' => $this->getLanguageCode()), 'condition' => array('WHERE' => 'id=' . $response['account']['id'])));
            } elseif ($response['account']['code'] != $this->getLanguageCode()) {
                if (isset($this->getAttribute('localization')['language'][$response['account']['code']])) {
                    $_SESSION[$this->getSessionLangCodeIndex()] = $response['account']['code'];
                }
            }
        } catch (Exception $e) {
            if (isset($_SESSION[$sess_jwt_key])) {
                unset($_SESSION[$sess_jwt_key]);
            }

            $log_message = $e->getMessage();
            $log_context = array(
                'reason' => 'attempt',
                'username' => $username ?? ''
            );
            
            $this->respondJSON(array('type' => 'danger', 'message' => $log_message));

            if ($this->isDevelopment()) {
                error_log($e->getMessage());
            }
        } finally {            
            $this->indolog('dashboard', LogLevel::NOTICE, $log_message, $log_context, $response['data']['account']['id'] ?? null);
        }
    }

    public function logout()
    {
        $sess_jwt_key = $this->getSessionJWTIndex();
        if (isset($_SESSION[$sess_jwt_key])) {
            $account = $this->getUser()->getAccount();
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системээс гарлаа.";
            $context = array('reason' => 'logout', 'jwt' => $_SESSION[$sess_jwt_key]);
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);

            unset($_SESSION[$sess_jwt_key]);
        }
        
        $this->redirectTo('home');
    }
    
    public function signup()
    {
        try {
            $requestBody = $this->getParsedBody();
            $username = $requestBody['username'];
            $password = $requestBody['password'];
            $passwordRe = $requestBody['password_re'];
            $email = filter_var($requestBody['email'], FILTER_SANITIZE_EMAIL);
            if (empty($username) || empty($email)
                || empty($password) || $password != $passwordRe
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $payload = array(
                'email' => $email,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'code' => $this->getLanguageCode(),
                'organization' => $this->getPostParam('organization_name')
            );
            
            $lookup = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='{$payload['code']}' AND p.keyword='request-new-account' AND p.is_active=1")));
            if (empty($lookup['request-new-account'])) {
                throw new Exception($this->text('email-template-not-set'));
            }
            $content = $lookup['request-new-account'];
            
            $response = $this->indopost('/account/signup', $payload);
            if (!isset($response['id'])) {
                throw new Exception($response['error']['message'] ?? $this->text('something-went-wrong'), $response['error']['code'] ?? 0);
            }
            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('username', $payload['username']);
            $template->source($content['full'][$payload['code']]);
            $email_response = $this->indo('/send/stmp/email', array(
                'name' => $payload['username'],
                'to' => $payload['email'],
                'code' => $payload['code'],
                'message' => $template->output(),
                'subject' => $content['title'][$payload['code']]
            ));
            if (empty($email_response['success']['message'])) {
                throw new Exception($email_response['error']['message'] ?? $this->text('something-went-wrong'), $email_response['error']['code'] ?? 0);
            }
            $this->respondJSON(array('type' => 'success', 'message' => $this->text('to-complete-registration-check-email')));
        } catch (Throwable $th) {
            switch ((int)$th->getCode()) {
                case AccountErrorCode::INSERT_DUPLICATE_EMAIL: {
                    $log_message = "Бүртгэлтэй [{$payload['email']}] хаягаар шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.";
                    $log_context = array(
                        'result' => 'email',
                        'email' => $payload['email'],
                        'username' => $payload['username'],
                        'organization' => $payload['organization'] ?? ''
                    );
                } break;
                case AccountErrorCode::INSERT_DUPLICATE_USERNAME: {
                    $log_message = "Бүртгэлтэй {$payload['username']} хэрэглэгчийн нэрээр шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.";
                    $log_context = array(
                        'result' => 'username',
                        'email' => $payload['email'],
                        'username' => $payload['username'],
                        'organization' => $payload['organization'] ?? ''
                    );
                } break;
                case AccountErrorCode::INSERT_DUPLICATE_NEWBIE: {
                    $log_message = "Шинээр {$payload['username']} нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав.";
                    $log_context = array(
                        'result' => 'newbie-duplicate',
                        'email' => $payload['email'],
                        'username' => $payload['username'],
                        'organization' => $payload['organization'] ?? ''
                    );
                } break;
                case AccountErrorCode::INSERT_NEWBIE_FAILURE: {
                    $log_message = "Шинээр {$payload['username']} нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.";
                    $log_context = array(
                        'result' => 'newbie',
                        'email' => $payload['email'],
                        'username' => $payload['username'],
                        'organization' => $payload['organization'] ?? ''
                    );
                } break;
            }
            
            if (isset($log_message) && isset($log_context)) {
                $this->respondJSON(array('type' => 'danger', 'message' => $log_message));
                
                $log_context['error'] = array(
                    'code' => $th->getCode(),
                    'message' => $th->getMessage()
                );
                $log_context['reason'] = 'request-new-account';
                $this->indolog('account', LogLevel::NOTICE, $log_message, $log_context);
            }  else {
                $this->respondJSON(array('type' => 'danger', 'message' => $th->getMessage()));
            }
        }
    }
    
    public function requestPassword()
    {
        try {
            $payload = array(
                'code' => $this->getLanguageCode(),
                'email' => $this->getParsedBody()['email'], 
                'login' => $this->generateLink('login', [], true));
            
            $lookup = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='{$payload['code']}' AND p.keyword='forgotten-password-reset' AND p.is_active=1")));
            if (empty($lookup['forgotten-password-reset'])) {
                throw new Exception($this->text('email-template-not-set'));
            }
            $content = $lookup['forgotten-password-reset'];
            
            $response = $this->indopost('/account/forgot', $payload);
            if (empty($response['use_id'])) {
                throw new Exception($response['error']['message'] ?? $this->text('something-went-wrong'), $response['error']['code'] ?? 0);
            }
            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('link', "{$payload['login']}?forgot={$response['use_id']}");
            $template->source($content['full'][$payload['code']]);
            $receiver = $response['first_name'] . ' ' . $response['last_name'];
            $email_response = $this->indo('/send/stmp/email', array(
                'name' => $receiver,
                'to' => $payload['email'],
                'code' => $payload['code'],
                'message' => $template->output(),
                'subject' => $content['title'][$payload['code']]
            ));
            if (empty($email_response['success']['message'])) {
                throw new Exception($email_response['error']['message'] ?? $this->text('something-went-wrong'), $email_response['error']['code'] ?? 0);
            }
            $this->respondJSON(array('type' => 'success', 'message' => $this->text('reset-email-sent')));
        } catch (Throwable $th) {
            switch ((int)$th->getCode()) {
                case AccountErrorCode::ACCOUNT_NOT_FOUND: {
                    $log_message = "Бүртгэлгүй [{$payload['email']}] хаяг дээр нууц үг шинээр тааруулах хүсэлт илгээхийг оролдлоо. Татгалзав.";
                    $log_context = array(
                        'result' => 'inactive',
                        'username' => $payload['email']
                    );
                } break;
                case AccountErrorCode::ACCOUNT_NOT_ACTIVE: {
                    $log_message = "Эрх нь нээгдээгүй хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээх оролдлого хийв. Татгалзав.";
                    $log_context = array(
                        'result' => 'not-found',
                        'username' => $payload['email']
                    );
                } break;
                case AccountErrorCode::INSERT_FORGOT_FAILURE: {
                    $log_message = "Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.";
                    $log_context = array(
                        'result' => 'forgot-failure',
                        'username' => $payload['email']
                    );
                } break;
            }
            
            if (isset($log_message) && isset($log_context)) {
                $this->respondJSON(array('type' => 'danger', 'message' => $log_message));
                
                $log_context['error'] = array(
                    'code' => $th->getCode(),
                    'message' => $th->getMessage()
                );
                $log_context['reason'] = 'request-password';
                $this->indolog('account', LogLevel::INFO, $log_message, $log_context);
            }  else {
                $this->respondJSON(array('type' => 'danger', 'message' => $th->getMessage()));
            }
        }
    }
    
    public function forgotPassword($use_id, $error = null)
    {
        try {
            $response = $this->indo('/record?model=' . ForgotModel::class, array('use_id' => $use_id, 'is_active' => 1));
            $forgot = $response['record'] ?? [];
            if (!isset($forgot['is_active'])) {
                throw new Exception('Хуурамч мэдээлэл ашиглан нууц үг тааруулахыг оролдлоо. Татгалзав.');
            }
            $code = $forgot['code'];
            if ($code != $this->getLanguageCode()) {
                if (isset($this->getAttribute('localization')['language'][$code])) {
                    $_SESSION[$this->getSessionLangCodeIndex()] = $code;
                    $link = $this->generateLink('login') . "?forgot=$use_id";
                    header("Location: $link", false, 302);
                    exit;
                }
            }
            
            $now_date = new DateTime();
            $then = new DateTime($forgot['created_at']);
            $diff = $then->diff($now_date);
            if ($diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > 5) {
                throw new Exception('Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв. Татгалзав.');
            }
            $vars = array(
                'use_id' => $use_id,
                'account' => $forgot['account'],
                'created_at' => $forgot['created_at']
            );
            if (!empty($error)) {
                $vars['error'] = $error;
            }
            $this->twigTemplate(dirname(__FILE__) . '/login-reset-password.html', $vars)->render();
        } catch (Throwable $th) {
            $this->twigTemplate(dirname(__FILE__) . '/login-forgot.html', array('notice' => $th->getMessage()))->render();
        } finally {
            // TODO: Nuuts ugee sergeeheer oroldoj buig log hiih
        }
    }
    
    public function setPassword()
    {
        try {
            $use_id = $this->getPostParam('use_id');
            $account = $this->getPostParam('account');
            $created_at = $this->getPostParam('created_at');
            $password_new = $this->getPostParam('password_new');
            $password_retype = $this->getPostParam('password_retype');
            if (empty($use_id) || empty($account) || empty($created_at)
                    || !isset($password_new) || !isset($password_retype)
            ) {
                return $this->redirectTo('home');
            }
            if (empty($password_new) || $password_new != $password_retype) {
                throw new Exception('Шалтгаан: Шинэ нууц үгээ буруу бичсэн.<br/>' . $this->text('password-must-match'));
            }
            $response = $this->indo('/record?model=' . ForgotModel::class, array('use_id' => $use_id, 'is_active' => 1));
            $forgot = $response['record'] ?? [];
            if (!isset($forgot['account']) || $forgot['account'] != $account) {
                throw new Exception('Шалтгаан: Мэдээлэл олдсонгүй!');
            }
            $payload = array(
                'use_id' => $use_id,
                'created_at' => $created_at,
                'account' => (int)$account,
                'password' => password_hash($password_new, PASSWORD_BCRYPT)
            );
            $account_response = $this->indopost('/account/password', $payload);
            if (!isset($account_response['id'])) {
                throw new Exception('Шалтгаан: Мэдээлэл буруу!');
            }
            $this->twigTemplate(dirname(__FILE__) . '/login-forgot.html', array(
                'title' => $this->text('success'),
                'notice' => $this->text('set-new-password-success')                
            ))->render();
            $this->indolog('account', LogLevel::INFO, 'Нууц үг шинээр тохируулав.', array('use_id' => $use_id, 'created_at' => $created_at, 'account' => $account_response));
        } catch (Throwable $th) {
            $log_message = 'Нууц үг шинээр тохируулах үйлдэл амжилтгүй боллоо.';
            
            $this->forgotPassword($use_id, $log_message . ' ' . $th->getMessage());
            
            $log_context['error'] = array(
                'code' => $th->getCode(),
                'message' => $th->getMessage(),
                'use_id' => $use_id,
                'account' => $account,
                'created_at' => $created_at
            );
            $log_context['reason'] = 'reset-password';
            $this->indolog('account', LogLevel::ALERT, $log_message, $log_context);
        }
    }
    
    public function selectOrganization($id)
    {
        if (!$this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }
        
        $current_org_id = $this->getUser()->getOrganization()['id'];
        if ($id == $current_org_id) {
            return $this->redirectTo('home');
        }
        
        $account_id = $this->getUser()->getAccount()['id'];
        $response = $this->indo('/record?model=' . OrganizationUserModel::class,
                array('account_id' => $account_id, 'organization_id' => $id, 'is_active' => 1));
        $record = $response['record'] ?? [];
        if (empty($record['organization_id'])) {
            if (!$this->getUser()->can('system_org_index')) {
                return $this->redirectTo('home');
            }
            
            $res = $this->indo('/record?model=' . OrganizationModel::class, array('id' => $id, 'is_active' => 1));
            $org = $res['record'] ?? [];
            if (!isset($org['id'])) {
                return $this->redirectTo('home');
            }
        }
        
        $jwt_info = array(
            'organization_id' => $id,
            'account_id' => (int)$account_id);
        $jwt_result = $this->indopost('/auth/organization', $jwt_info);
        if (!empty($jwt_result['jwt'])) {
            $_SESSION[$this->getSessionJWTIndex()] = $jwt_result['jwt'];
            $message = "Хэрэглэгч {$this->getUser()->getAccount()['first_name']} {$this->getUser()->getAccount()['last_name']} нэвтэрсэн байгууллага сонгов.";
            $context = array('reason' => 'login-to-organization', 'enter' => $id, 'leave' => $current_org_id, 'jwt' => $jwt_result['jwt']);
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
        }

        $this->redirectTo('home');
    }
}
