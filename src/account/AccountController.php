<?php

namespace Raptor\Account;

use Exception;
use Throwable;
use DateTime;

use Twig\TwigFunction;
use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Auth\OrganizationModel;
use Indoraptor\Auth\OrganizationUserModel;

use Raptor\Authentication\ForgotModel;
use Raptor\Authentication\AccountRequestsModel;
use Raptor\File\PrivateFileController;
use Raptor\Dashboard\DashboardController;

class AccountController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['accounts'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['accounts'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        try {
            $context = array();
                
            if (!$this->isUserCan('system_account_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $accounts = $this->indo('/record/rows?model=' . Accounts::class);
            $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
            $statuses = $this->indo('/lookup', array(
                'table' => 'status', 'condition' => array('WHERE' => "c.code='$code' AND p.is_active=1")));
            
            $org_users_query =
                'SELECT t1.account_id, t1.organization_id ' .
                'FROM indo_organization_users as t1 JOIN indo_organizations as t2 ON t1.organization_id=t2.id ' .
                'WHERE t1.is_active=1 AND t2.is_active=1';
            $org_users = $this->indo('/statement', array('query' => $org_users_query));
            array_walk($org_users, function($value) use (&$accounts) {
                if (isset($accounts[$value['account_id']])) {
                    if (!isset($accounts[$value['account_id']]['organizations'])) {
                        $accounts[$value['account_id']]['organizations'] = array();
                    }
                    $accounts[$value['account_id']]['organizations'][] = $value['organization_id'];
                }
            });
            
            $user_role_query =
                'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                'FROM rbac_user_role as t1 JOIN rbac_roles as t2 ON t1.role_id=t2.id WHERE t1.is_active=1';
            $user_role = $this->indo('/statement', array('query' => $user_role_query));
            array_walk($user_role, function($value) use (&$accounts) {
                if (isset($accounts[$value['user_id']])) {
                    if (!isset($accounts[$value['user_id']]['roles'])) {
                        $accounts[$value['user_id']]['roles'] = array();
                    }
                    $accounts[$value['user_id']]['roles'][] = "{$value['alias']}_{$value['name']}";
                }
            });
            
            $this->twigDashboard(dirname(__FILE__) . '/account-index.html',
                array('accounts' => $accounts, 'statuses' => $statuses, 'organizations' => $organizations))->render();
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэж байна';
        } catch (Throwable $e) {
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэх үед алдаа гарлаа';
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
            
            $this->dashboardProhibited("$message.<br/><br/>{$e->getMessage()}", $e->getCode())->render();
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function insert()
    {
        try {
            $context = array();
            
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($this->getRequest()->getMethod() == 'POST') {                
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || filter_var($parsedBody['email'], FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $record = array(
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => filter_var($parsedBody['email'], FILTER_VALIDATE_EMAIL)
                );
                if (empty($parsedBody['password'])) {
                    $bytes = random_bytes(10);
                    $password = bin2hex($bytes);
                } else {
                    $password = $parsedBody['password'];
                }
                $record['password'] = password_hash($password , PASSWORD_BCRYPT);
                
                $status = $parsedBody['status'] ?? 'off';
                $record['status'] = $status != 'on' ? 0 : 1;                
                $id = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
                
                if (!empty($parsedBody['organization'] ?? null)) {
                    $organization = filter_var($parsedBody['organization'], FILTER_VALIDATE_INT);
                    if ($organization !== false) {
                        $org_exists = $this->indosafe('/record?model=' . OrganizationModel::class, array('id' => $organization));
                        if ($org_exists !== false) {
                            $this->indopost('/record?model=' . OrganizationUserModel::class, array(
                                'record' => array('organization_id' => $organization, 'account_id' => $id)
                            ));
                            $context += array('organization' => $organization);
                        }
                    }
                }
                
                $file = new PrivateFileController($this->getRequest());
                $file->init("/accounts/$id");
                $file->allowType(3);
                $photo = $file->moveUploaded('photo');
                if (isset($photo['name'])) {
                    $photo_path = $file->getPathUrl($photo['name']);
                    $payload = array(
                        'record' => array('photo' => $photo_path),
                        'condition' => array('WHERE' => "id=$id")
                    );
                    $this->indoput('/record?model=' . Accounts::class, $payload);                    
                    $context += array('photo' => $photo_path);
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('accounts')
                ));
                
                $level = LogLevel::INFO;
                $context += array('id' => $id, 'record' => $record);
                $message = 'Хэрэглэгч үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
            } else {
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
                $this->twigDashboard(dirname(__FILE__) . '/account-insert.html', array('organizations' => $organizations))->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function update(int $id)
    {
        try {
            $context = array();
            
            if (!$this->isUserAuthorized()
                || (!$this->getUser()->can('system_account_update')
                    && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($id == 1 && $this->getUser()->getAccount()['id'] != $id) {
                throw new Exception('No one but root can edit this account!', 403);
            }
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || filter_var($parsedBody['email'], FILTER_VALIDATE_EMAIL) === false
                ) { 
                    throw new Exception($this->text('invalid-request'), 400);
                }

                $record = array(
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => filter_var($parsedBody['email'], FILTER_VALIDATE_EMAIL)
                );
                if (!empty($parsedBody['password'])) {
                    $record['password'] = password_hash($parsedBody['password'] , PASSWORD_BCRYPT);
                }
                if ($this->getUser()->is('system_coder')) {
                    $status = $parsedBody['status'] ?? 'off';
                    $record['status'] = $status != 'on' ? 0 : 1;
                }
                $context = array('record' => $record + ['id' => $id]);
                
                $pattern = '/record?model=' . Accounts::class;
                
                $existing_username = $this->indosafe($pattern, array('username' => $record['username']));
                if ($existing_username && $existing_username['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " username => [{$record['username']}]", 403);
                }
                $existing_email = $this->indosafe($pattern, array('email' => $record['email']));
                if ($existing_email && $existing_email['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " email => [{$record['email']}]", 403);
                }
                
                $existing = $this->indosafe($pattern, array('id' => $id));
                $old_photo_file = basename($existing['photo'] ?? '');
                $file = new PrivateFileController($this->getRequest());
                $file->init("/accounts/$id");
                $file->allowType(3);
                $photo = $file->moveUploaded('photo');
                if (isset($photo['name'])) {
                    $record['photo'] = $file->getPathUrl($photo['name']);
                }
                if (!empty($old_photo_file)) {
                    if ($file->getLastError() == -1) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/../private/accounts/$id/$old_photo_file");
                        $record['photo'] = '';
                    } else if (isset($photo['name']) && $photo['name'] != $old_photo_file) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/../private/accounts/$id/$old_photo_file");
                    }
                }
                if (isset($record['photo'])) {
                    $context['photo'] = $record['photo'];
                }
                
                $this->indoput($pattern, array('record' => $record, 'condition' => ['WHERE' => "id=$id"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('accounts')
                ));
                
                $organizations = array();
                $post_organizations = filter_var($parsedBody['organizations'] ?? array(), FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $org_id) {
                    $organizations[$org_id] = true;
                }

                $org_user = $this->indo('/statement', array(
                    'query' => "SELECT id,organization_id FROM indo_organization_users WHERE account_id=$id AND is_active=1"));
                foreach ($org_user as $row) {
                    if (isset($organizations[(int)$row['organization_id']])) {
                        unset($organizations[(int)$row['organization_id']]);
                    } else if ($row['organization_id'] == 1 && $id == 1) {
                        // can't strip root account from system organization!
                    } else {
                        $this->indodelete('/record?model=' . OrganizationUserModel::class, array('WHERE' => "id={$row['id']}"));
                        $this->indolog(
                            'account',
                            LogLevel::ALERT,
                            "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $id дугаар бүхий хэрэглэгчийг хаслаа",
                            array('reason' => 'organization-strip', 'account_id' => $id, 'organization_id' => $row['organization_id'])
                        );
                    }
                }

                foreach (array_keys($organizations) as $org_id) {
                    $this->indopost('/record?model=' . OrganizationUserModel::class,
                        array('record' => array('account_id' => $id, 'organization_id' => $org_id)));
                    $this->indolog(
                        'account',
                        LogLevel::ALERT,
                        "$id дугаартай хэрэглэгчийг $org_id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                        array('reason' => 'organization-set', 'account_id' => $id, 'organization_id' => $org_id)
                    );
                }
                
                if ($this->isUserCan('system_rbac')) {
                    $post_roles = filter_var($parsedBody['roles'] ?? array(), FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
                    $roles = array();
                    foreach ($post_roles as $role) {
                        $roles[$role] = true;
                    }

                    $user_role = $this->indo('/statement', array(
                        'query' => "SELECT id,role_id FROM rbac_user_role WHERE user_id=$id AND is_active=1"));
                    foreach ($user_role as $row) {
                        if (isset($roles[(int)$row['role_id']])) {
                            unset($roles[(int)$row['role_id']]);
                        } else if ($row['role_id'] == 1 && $id === 1) {
                            // can't delete root account's coder role!
                        } else if ($row['role_id'] == 1 && !$this->getUser()->is('system_coder')) {
                            // only coder can strip another coder role
                        } else {
                            $this->indodelete('/record?model=' . UserRole::class, array('WHERE' => "id={$row['id']}"));
                            $this->indolog(
                                'rbac',
                                LogLevel::ALERT,
                                "$id дугаартай хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
                                array('reason' => 'role-strip', 'account_id' => $id, 'role_id' => $row['role_id'])
                            );
                        }
                    }
                    
                    foreach (array_keys($roles) as $role_id) {
                        if ($role_id == 1 && !$this->getUser()->is('system_coder')) {
                            // only coder can add another coder role
                            continue;
                        }
                        $this->indopost('/record?model=' . UserRole::class,
                            array('record' => array('user_id' => $id, 'role_id' => $role_id)));   
                        $this->indolog(
                            'rbac',
                            LogLevel::ALERT,
                            "$id дугаартай хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                            array('reason' => 'role-set', 'account_id' => $id, 'role_id' => $role_id)
                        );
                    }
                }
                
                $level = LogLevel::INFO;
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id));
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
                $vars = array('record' => $record, 'organizations' => $organizations);
             
                $org_id_query =
                    'SELECT ou.organization_id as id ' .
                    'FROM indo_organization_users as ou JOIN indo_organizations as o ON ou.organization_id=o.id ' .
                    "WHERE ou.account_id=$id AND ou.is_active=1 AND o.is_active=1";
                $org_ids = $this->indo('/statement', array('query' => $org_id_query));
                $ids = array();
                foreach ($org_ids as $org) {
                    $ids[] = $org['id'];
                }
                $vars['current_organizations'] = implode(',', $ids);
                
                $rbacs = array('common' => 'Common');            
                $alias_names = $this->indo('/statement', array(
                    'query' => "SELECT alias,name FROM indo_organizations WHERE alias!='common' AND is_active=1 ORDER By id desc"));
                foreach ($alias_names as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = array_map(function() { return array(); }, array_flip(array_keys($rbacs)));
                $rbac_roles = $this->indo('/statement', array(
                    'query' => 'SELECT id,alias,name,description FROM rbac_roles WHERE is_active=1'));
                array_walk($rbac_roles, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = array();
                    }
                    $roles[$value['alias']][$value['id']] = array($value['name']);

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $current_role_query = 'SELECT rur.role_id FROM rbac_user_role as rur INNER JOIN rbac_roles as rr ON rur.role_id=rr.id ' .
                                      "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->indo('/statement', array('query' => $current_role_query));
                $current_role = array();
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = implode(',', $current_role);
                
                $this->twigDashboard(dirname(__FILE__) . '/account-update.html', $vars)->render();
                
                $level = LogLevel::NOTICE;
                $context += array(
                    'record' => $record,
                    'current_role' => $vars['current_role'],
                    'current_organizations' => $vars['current_organizations']
                );
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ";
            }
        } catch (Throwable $e) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context = array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                || (!$this->getUser()->can('system_account_index')
                && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id));
            
            $organizations_query =
                'SELECT t2.name ' .
                'FROM indo_organization_users as t1 JOIN indo_organizations as t2 ON t1.organization_id=t2.id ' .
                "WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=$id";
            $organizations = $this->indo('/statement', array('query' => $organizations_query));

            $user_role_query =
                'SELECT CONCAT(t2.alias, "_", t2.name) as name ' . 
                'FROM rbac_user_role as t1 JOIN rbac_roles as t2 ON t1.role_id=t2.id ' .
                "WHERE t1.is_active=1 AND t1.user_id=$id";
            $user_roles = $this->indo('/statement', array('query' => $user_role_query));
            
            $this->twigDashboard(dirname(__FILE__) . '/account-view.html', array(
                'record' => $record, 'roles' => $user_roles, 'organizations' => $organizations, 'accounts' => $this->getAccounts()
            ))->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['username']} хэрэглэгчийн мэдээллийг нээж үзэж байна";
            $context = array('record' => $record, 'roles' => $user_roles, 'organizations' => $organizations);
        } catch (Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна';
            $context = array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_account_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }
            
            if ($this->getUser()->getAccount()['id'] == $payload['id']) {
                throw new Exception('Cannot suicide myself :(', 403);
            } else if ($payload['id'] == 1) {
                throw new Exception('Cannot remove first acccount!', 403);
            }
            
            $this->indodelete("/record?{$table}model=" . Accounts::class, array('WHERE' => "id='{$payload['id']}'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $context = array('payload' => $payload);
            $message = "{$payload['name']} хэрэглэгчийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context = array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function requestsModal($table)
    {
        try {
            if (!$this->isUserCan('system_account_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }

            if (!in_array($table, array('forgot', 'newbie'))) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            
            $modal = dirname(__FILE__) . "/$table-index-modal.html";
            if (!file_exists($modal)) {
                throw new Exception("$modal file not found!", 500);
            }

            if ($table == 'forgot') {
                $modelName = ForgotModel::class;
                $message = 'Нууц үгээ сэргээх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            } else {
                $modelName = AccountRequestsModel::class;
                $message = 'Шинэ хэрэглэгчээр бүртгүүлэх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            }
            $vars = array(
                'rows' => $this->indo("/record/rows?model=$modelName", array('WHERE' => 'is_active!=999'))
            );        

            $template = $this->twigTemplate($modal, $vars);
            $template->addFunction(new TwigFunction('isExpired', function ($date, $minutes = 5): bool
            {
                $now_date = new DateTime();
                $then = new DateTime($date);
                $diff = $then->diff($now_date);
                return $diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > $minutes;
            }));
            $template->render();
            
            $level = LogLevel::NOTICE;
            $context = array('model' => $modelName, 'table' => $table);
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = "Хэрэглэгчдийн мэдээллийн хүснэгт [$table] нээж үзэх хүсэлт алдаатай байна";
            $context = array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function requestApprove()
    {
        try {
            $context = array('reason' => 'account-request-approve');
            
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception('No permission for an action [approval]!', 401);
            }
            
            $parsedBody = $this->getParsedBody();
            $id = $parsedBody['id'] ?? null;
            if (empty($id)
                || !filter_var($id, FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context += array('payload' => $parsedBody, 'id' => $id);
            
            $record = $this->indo('/record?model=' . AccountRequestsModel::class, array('id' => $id));
            $existing = $this->indo('/statement', array(
                'query' => 'SELECT id FROM rbac_accounts WHERE username=:username OR email=:email',
                'bind' => array(
                    ':email' => array('var' => $record['email']),
                    ':username' => array('var' => $record['username'])
                )
            ));
            if (!empty($existing)) {
                throw new Exception($this->text('account-exists') . "<br/>username/email => {$record['username']}/{$record['email']}", 403);
            }
            
            unset($record['id']);
            unset($record['status']);
            unset($record['is_active']);
            unset($record['created_at']);
            unset($record['created_by']);
            unset($record['updated_at']);
            unset($record['updated_by']);
            unset($record['rbac_account_id']);
            $account_id = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
            $context += array('account' => $record + ['id' => $account_id]);

            $payload = array(
                'condition' => array('WHERE' => "id=$id"),
                'record' => array('is_active' => 0, 'status' => 2, 'rbac_account_id' => $account_id)
            );
            $this->indoput('/record?model=' . AccountRequestsModel::class, $payload);            
            
            $organization_id = filter_var($parsedBody['organization_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($organization_id === false && $organization_id > 0) {
                $organization_id = 1;
            }            
            $posted = $this->indosafe(
                '/record?model=' . OrganizationUserModel::class,
                array('record' => array('account_id' => $account_id, 'organization_id' => $organization_id)),
                'POST');
            if (!empty($posted)) {
                $context += array('organization' => $organization_id);
            }
            
            $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $lookup = $this->indosafe('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='$code' AND p.keyword='approve-new-account' AND p.is_active=1")));
            if (isset($lookup['approve-new-account'])) {
                $content = $lookup['approve-new-account'];
                
                $template = new MemoryTemplate();
                $template->source($content['full'][$code]);
                $template->set('email', $record['email']);
                $template->set('login', $this->generateLink('login', [], true));
                $template->set('username', $record['username']);
                $this->indosafe('/send/smtp/email', array(
                    'name' => $record['username'],
                    'to' => $record['email'],
                    'code' => $record['code'],
                    'message' => $template->output(),
                    'subject' => $content['title'][$code]
                ));
            }
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-insert-success')
            ));
            
            $level = LogLevel::ALERT;
            $message = "Шинэ бүртгүүлсэн {$record['username']} нэртэй [{$record['email']}] хаягтай хэрэглэгчийн хүсэлтийг зөвшөөрч системд нэмлээ";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
    
    public function requestDelete()
    {        
        try {
            $context = array('reason' => 'account-request-delete', 'table' => 'newbie');
            
            if (!$this->isUserCan('system_account_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context += array('payload' => $payload);
            
            $this->indodelete("/record?model=" . AccountRequestsModel::class, array('WHERE' => "id='{$payload['id']}'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчээр бүртгүүлэх хүсэлтийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context += array('error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } finally {
            $this->indolog('account', $level, $message, $context + array('model' => Accounts::class));
        }
    }
}
