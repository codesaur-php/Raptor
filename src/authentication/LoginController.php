<?php

namespace Raptor\Authentication;

use Psr\Log\LogLevel;
use Fig\Http\Message\StatusCodeInterface;

use codesaur\RBAC\Accounts;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Auth\OrganizationUserModel;

\define('CODESAUR_PASSWORD_RESET_MINUTES', $_ENV['CODESAUR_PASSWORD_RESET_MINUTES'] ?? 10);

class LoginController extends \Raptor\Controller
{
    public function index()
    {
        $forgot_id = $this->getQueryParams()['forgot'] ?? false;
        if (!empty($forgot_id)) {
            return $this->forgotPassword($forgot_id);
        }
        
        if ($this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }
        
        $vars = (array)$this->getAttribute('settings');
        try {
            $code = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $vars += $this->indo(
                '/reference/templates', 
                ['WHERE' => "c.code='$code' AND (p.keyword='tos' OR p.keyword='pp') AND p.is_active=1"]
            );
        } catch (\Throwable $e) {
        }
        
        $this->twigTemplate(\dirname(__FILE__) . '/login.html', $vars)->render();
    }
    
    public function entry()
    {
        try {
            $context = ['reason' => 'login'];
            $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
            $payload = $this->getParsedBody();
            
            $context += ['payload' => $payload];
            if (isset($context['payload']['password'])) {
                unset($context['payload']['password']);
            }

            if ($this->isUserAuthorized() || empty($payload)) {
                throw new \Exception($this->text('invalid-request'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }

            $account = $this->indopost('/auth/entry', $payload);
            $_SESSION[$sess_jwt_key] = $account['jwt'];

            $level = LogLevel::INFO;
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системд нэвтрэв.";
            $this->respondJSON(['status' => 'success', 'message' => $message]);

            if (empty($account['code'])) {
                try {
                    $this->indo(
                        '/record/update?model=' . Accounts::class,
                        [
                            'record' => ['code' => $this->getLanguageCode()],
                            'condition' => ['WHERE' => "id={$account['id']}"]
                        ]
                    );
                } catch (\Throwable $e) {
                }
            } elseif ($account['code'] != $this->getLanguageCode()
                && isset($this->getLanguages()[$account['code']])
            ) {
                $_SESSION[\explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $account['code'];
            }
        } catch (\Throwable $e) {
            if (isset($_SESSION[$sess_jwt_key])) {
                unset($_SESSION[$sess_jwt_key]);
            }
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());

            $this->errorLog($e);
            
            $level = LogLevel::ERROR;
            $message = $e->getMessage();
            $context += ['reason' => 'attempt', 'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('dashboard', $level, $message, $context, $account['id'] ?? null);
        }
    }

    public function logout()
    {
        $sess_jwt_key = __NAMESPACE__ . '\\indo\\jwt';
        if (isset($_SESSION[$sess_jwt_key])) {
            $account = $this->getUser()->getAccount();
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системээс гарлаа.";
            $context = ['reason' => 'logout', 'jwt' => $_SESSION[$sess_jwt_key]];
            
            unset($_SESSION[$sess_jwt_key]);
            
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
        }
        
        $this->redirectTo('home');
    }
    
    public function signup()
    {
        try {
            $context = ['reason' => 'request-new-account'];
            
            $payload = $this->getParsedBody();
            $context += ['payload' => $payload];
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
                throw new \Exception($this->text('invalid-request'), StatusCodeInterface::STATUS_BAD_REQUEST);
            } else {
                unset($payload['password_re']);
            }

            $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);
            $payload['code'] = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            
            try {
                $reference = $this->indo(
                    '/reference/templates', 
                    ['WHERE' => "c.code='{$payload['code']}' AND p.keyword='request-new-account' AND p.is_active=1"]
                );
                $content = $reference['request-new-account'];
            } catch (\Throwable $e) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            if (empty($payload['email']) || empty($payload['username'])) {
                throw new \Exception('Invalid payload', StatusCodeInterface::STATUS_BAD_REQUEST);
            } elseif (\filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false) {
                throw new \Exception('Please provide valid email address.', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            try {
                $existing_email = $this->indo('/record?model=' . Accounts::class, ['email' => $payload['email']]);
            } catch (\Throwable $e) {
            }
            
            try {
                $existing_username = $this->indo('/record?model=' . Accounts::class, ['username' => $payload['username']]);
            } catch (\Throwable $e) {
            }
            
            if (!empty($existing_email)) {
                throw new \Exception("Бүртгэлтэй [{$payload['email']}] хаягаар шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            } elseif (!empty($existing_username)) {
                throw new \Exception("Бүртгэлтэй [{$payload['username']}] хэрэглэгчийн нэрээр шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            try {
                $requests_email = $this->indo(
                    '/record?model=' . AccountRequestsModel::class,
                    ['email' => $payload['email'], 'status' => 1, 'is_active' => 1]
                );
            } catch (\Throwable $e) {
            }
            
            try {
                $requests_username = $this->indo(
                    '/record?model=' . AccountRequestsModel::class,
                    ['username' => $payload['username'], 'status' => 1, 'is_active' => 1]
                );
            } catch (\Throwable $e) {
            }
            
            if (!empty($requests_email)) {
                throw new \Exception("Шинээр [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            } elseif (!empty($requests_username)) {
                throw new \Exception("Шинээр [{$payload['username']}] нэртэй хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            try {
                $id = $this->indo('/record/insert?model=' . AccountRequestsModel::class, $payload);
            } catch (\Throwable $e) {
            }
            
            if (empty($id)) {
                throw new \Exception("Шинээр [{$payload['username']}] нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('username', $payload['username']);
            $template->source($content['full'][$payload['code']]);
            try {
                $this->indo(
                    '/send/mail',
                    [
                        'to' => $payload['email'],
                        'message' => $template->output(),
                        'subject' => $content['title'][$payload['code']]
                    ]
                );
                $this->respondJSON(['status' => 'success', 'message' => $this->text('to-complete-registration-check-email')]);
            } catch (\Throwable $e) {
                $this->respondJSON(['status' => 'success', 'message' => 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг хүлээн авлаа!']);
            }
            
            $level = LogLevel::ALERT;
            $message = "{$payload['username']} нэртэй {$payload['email']} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ";
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүлэх үед алдаа гарч зогслоо.</span><br/>' . $message], $e->getCode());
            
            $level = LogLevel::ERROR;
            $context += ['error' => ['code' => $e->getCode(), 'message' => $message]];
        } finally {
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'request-new-account', $context);
        }
    }
    
    public function forgot()
    {
        try {
            $context = ['reason' => 'login-forgot'];
            
            $payload = $this->getParsedBody();
            if (empty($payload['email'])
                || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \Exception('Please provide valid email address', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            try {
                $payload['code'] = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
                $reference = $this->indo(
                    '/reference/templates',
                    ['WHERE' => "c.code='{$payload['code']}' AND p.keyword='forgotten-password-reset' AND p.is_active=1"]
                );
                $content = $reference['forgotten-password-reset'];
            } catch (\Throwable $e) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_NOT_FOUND);
            }
            
            try {
                $account = $this->indo('/record?model=' . Accounts::class, ['email' => $payload['email']]);                
            } catch (\Throwable $e) {
            }
            
            if (empty($account)) {
                throw new \Exception("Бүртгэлгүй [{$payload['email']}] хаяг дээр нууц үг шинээр тааруулах хүсэлт илгээхийг оролдлоо. Татгалзав.", StatusCodeInterface::STATUS_NOT_FOUND);
            }
            if ($account['is_active'] != 1) {
                throw new \Exception("Эрх нь нээгдээгүй хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээх оролдлого хийв. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            try {
                $record = [
                    'status'      => 1,
                    'use_id'      => \uniqid('use'),
                    'account'     => $account['id'],
                    'email'       => $account['email'],
                    'code'        => $payload['code'],
                    'username'    => $account['username'],
                    'last_name'   => $account['last_name'],
                    'first_name'  => $account['first_name'],
                    'remote_addr' => $this->getRemoteAddr()
                ];
                $id = $this->indo('/record/insert?model=' . ForgotModel::class, $record);
            } catch (\Throwable $e) {
                $this->errorLog($e);
            }
            
            if (empty($id)) {
                throw new \Exception("Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            $level = LogLevel::INFO;
            $message = "{$payload['email']} хаягтай хэрэглэгч  нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгүүллээ";

            try {
                $record['id'] = $id;
                $context += ['forgot' => $record];

                $template = new MemoryTemplate();
                $template->set('email', $payload['email']);
                $template->set('minutes', CODESAUR_PASSWORD_RESET_MINUTES);
                $template->set('link', "{$this->generateLink('login', [], true)}?forgot={$record['use_id']}");
                $template->source($content['full'][$payload['code']]);
                $this->indo(
                    '/send/mail',
                    [
                        'to' => $payload['email'],
                        'message' => $template->output(),
                        'subject' => $content['title'][$payload['code']]
                    ]
                );
                $this->respondJSON(['status' => 'success', 'message' => $this->text('reset-email-sent')]);
            } catch (\Throwable $e) {
                $this->respondJSON(['status' => 'success', 'message' => $message]);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Хэрэглэгч нууц үгээ шинэчлэх хүсэлт илгээх үед алдаа гарч зогслоо.</span><br/>' . $message], $e->getCode());

            $level = LogLevel::ERROR;
            $context += ['error' => ['code' => $e->getCode(), 'message' => $message]];
        } finally {
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'login-forgot', $context);
        }
    }
    
    public function forgotPassword(string $use_id)
    {
        try {
            $context = ['reason' => 'forgot-password', 'use_id' => $use_id];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['use_id'] = $use_id;
            
            $forgot = $this->indo(
                '/record?model=' . ForgotModel::class,
                ['use_id' => $use_id, 'status' => 1,'is_active' => 1]
            );
            $code = $forgot['code'];
            if ($code != $this->getLanguageCode()) {
                if (isset($this->getLanguages()[$code])) {
                    $_SESSION[\explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $code;
                    $link = $this->generateLink('login') . "?forgot=$use_id";
                    \header("Location: $link", false, 302);
                    exit;
                }
            }
            $context += ['forgot' => $forgot];
            
            $now_date = new \DateTime();
            $then = new \DateTime($forgot['created_at']);
            $diff = $then->diff($now_date);
            if ($diff->y > 0 || $diff->m > 0 || $diff->d > 0
                || $diff->h > 0 || $diff->i > (int) CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new \Exception('Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            $vars += ['use_id' => $use_id, 'account' => $forgot['account']];
            
            $level = LogLevel::ALERT;
            $message = 'Нууц үгээ шинээр тааруулж эхэллээ.';
        } catch (\Throwable $e) {
            if ($e->getCode() == 404) {
                $notice = 'Хуурамч/устгагдсан/хэрэглэгдсэн мэдээлэл ашиглан нууц үг тааруулахыг оролдов';
            } else {
                $notice = $e->getMessage();
            }
            $vars += ['title' => $this->text('error'), 'notice' => $notice];

            $level = LogLevel::ERROR;
            $message = "Нууц үгээ шинээр тааруулж эхлэх үед алдаа гарч зогслоо. $notice";
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars)->render();
            
            $context += ['server_request' => [
                'code' => $this->getLanguageCode(),
                'method' => $this->getRequest()->getMethod(),
                'remote_addr' => $this->getRemoteAddr()
            ]];
            
            $this->indolog('dashboard', $level, $message, $context, $forgot['account'] ?? null);
        }
    }
    
    public function setPassword()
    {
        try {
            $context = ['reason' => 'reset-password'];
            $parsedBody = $this->getParsedBody();
            $use_id = $parsedBody['use_id'];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['use_id'] = $use_id;
            $context += ['payload' => $parsedBody, 'use_id' => $use_id];
            if (isset($parsedBody['password_new'])) {
                $password_new = $parsedBody['password_new'];
                unset($context['payload']['password_new']);
            } else {
                $password_new = null;
            }
            if (isset($parsedBody['password_retype'])) {
                $password_retype = $parsedBody['password_retype'];
                unset($context['payload']['password_retype']);
            } else {
                $password_retype = null;
            }
            $account_id = \filter_var($parsedBody['account'], \FILTER_VALIDATE_INT);
            if ($account_id === false) {
                throw new \Exception('<span class="text-secondary">Хэрэглэгчийн дугаар заагдаагүй байна.</span><br/>' . $this->text('invalid-request-data'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            $vars += ['account' => $account_id];

            if (empty($use_id) || empty($account_id)
                    || !isset($password_new) || !isset($password_retype)
            ) {
                return $this->redirectTo('home');
            }
            $context += ['account_id' => $account_id];
            
            if (empty($password_new) || $password_new != $password_retype) {
                throw new \Exception('<span class="text-secondary">Шинэ нууц үгээ буруу бичсэн.</span><br/>' . $this->text('password-must-match'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            try {
                $record = $this->indo(
                    '/record?model=' . ForgotModel::class,
                    [
                        'status' => 1,
                        'use_id' => $use_id,
                        'account' => $account_id,
                        'remote_addr' => $this->getRemoteAddr()
                    ]
                );
            } catch (\Throwable $e) {
                throw new \Exception('Unauthorized! ' . $e->getMessage(), StatusCodeInterface::STATUS_UNAUTHORIZED);
            }
            
            $account = $this->indo('/record?model=' . Accounts::class, ['id' => $account_id]);
            if (!$account) {
                throw new \Exception('Invalid account', StatusCodeInterface::STATUS_NOT_FOUND);
            }

            $result = $this->indo('/record/update?model=' . Accounts::class, [
                'record' => ['password' => \password_hash($password_new, \PASSWORD_BCRYPT)],
                'condition' => ['WHERE' => "id={$account['id']}"]
            ]);
            if (empty($result)) {
                throw new \Exception("Can't reset account [{$account['username']}] password", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $updated = $this->indo(
                '/record/update?model=' . ForgotModel::class,
                ['record' => ['status' => 0], 'condition' => ['WHERE' => "id={$record['id']}"]]
            );
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $vars += ['title' => $this->text('success'), 'notice' => $this->text('set-new-password-success')];

            $level = LogLevel::INFO;
            $message = 'Нууц үг шинээр тохируулав';
            $context += ['account' => $account];
        } catch (\Throwable $e) {
            $vars['error'] = $e->getMessage();
            
            $level = LogLevel::ERROR;
            $message = 'Шинээр нууц үг тааруулах үед алдаа гарлаа';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars)->render();
            
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'reset-password', $context, $account['id'] ?? null);
        }
    }
    
    public function selectOrganization(int $id)
    {
        $home = $this->generateLink('home');
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }
        
        try {
            if (!$this->isUserAuthorized() || $id == 0) {
                throw new \Exception('Unauthorized', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }

            $current_org_id = $this->getUser()->getOrganization()['id'];
            if ($id == $current_org_id) {
                throw new \Exception("Organization [$id] currently selected", StatusCodeInterface::STATUS_BAD_REQUEST);
            }

            $account_id = $this->getUser()->getAccount()['id'];
            $payload = ['account_id' => $account_id, 'organization_id' => $id];
            if (!$this->isUser('system_coder')) {
                $this->indoget('/record?model=' . OrganizationUserModel::class, $payload + ['is_active' => 1]);
            }
            $jwt_result = $this->indopost('/auth/organization', $payload);
            $_SESSION[__NAMESPACE__ . '\\indo\\jwt'] = $jwt_result['jwt'];
            
            $account = $this->getUser()->getAccount();
            $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} нэвтэрсэн байгууллага сонгов.";
            $context = ['reason' => 'login-to-organization', 'enter' => $id, 'leave' => $current_org_id, 'jwt' => $jwt_result['jwt']];
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
        } catch (\Throwable $e) {
            $this->errorLog($e);
        } finally {
            \header("Location: $location", false, 302);
            
            exit;
        }
    }
    
    public function language(string $code)
    {
        $target_path = $this->getTargetPath();
        $home = (string) $this->getRequest()->getUri()->withPath($target_path);
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }

        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code != $from) {
            $_SESSION[\explode('\\', __NAMESPACE__)[0] . '\\language\\code'] = $code;
            if ($this->isUserAuthorized()) {
                try {
                    $account = $this->getUser()->getAccount();
                    $payload = [
                        'record' => ['code' => $code],
                        'condition' => ['WHERE' => "id={$account['id']}"]
                    ];
                    $this->indoput('/record?model=' . Accounts::class, $payload);
                    
                    $message = "Хэрэглэгч {$account['first_name']} {$account['last_name']} системд ажиллах хэлийг $from-с $code болгон өөрчиллөө";
                    $context = ['reason' => 'change-language', 'code' => $code, 'from' => $from];
                    $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
                } catch (\Throwable $e) {
                    $this->errorLog($e);
                }
            }
        }
        
        \header("Location: $location", false, 302);
        exit;
    }
}
