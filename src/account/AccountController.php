<?php

namespace Raptor\Account;

use Exception;
use Throwable;
use DateTime;

use Psr\Log\LogLevel;
use Twig\TwigFunction;

use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Account\OrganizationModel;
use Indoraptor\Account\OrganizationUserModel;
use Indoraptor\Account\ForgotModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;
use Raptor\File\File;

class AccountController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('accounts'));
        $context = array('model' => Accounts::class);
        
        try {
            if (!$this->isUserCan('system_account_index')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $accounts = $this->indo('/record/rows?model=' . Accounts::class);
            $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
            $statuses = $this->indo('/lookup', array('table' => 'status', 'condition' => array('WHERE' => "c.code='$code' AND p.is_active=1")));
            
            $org_users_query =
                    'SELECT t1.account_id, t1.organization_id ' .
                    'FROM organization_users as t1 JOIN organizations as t2 ON t1.organization_id=t2.id ' .
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
            
            $template->render($this->twigTemplate(dirname(__FILE__) . '/account-index.html',
                    array('accounts' => $accounts, 'statuses' => $statuses, 'organizations' => $organizations)));
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэж байна';
        } catch (Throwable $e) {            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            
            $template->alertNoPermission("$message. {$e->getMessage()}");
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function insert()
    {
        $context = array('model' => Accounts::class);
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {            
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($is_submit) {
                $record = array(
                    'username' => $this->getPostParam('username'),
                    'password' => password_hash($this->getPostParam('password'), PASSWORD_BCRYPT),
                    'first_name' => $this->getPostParam('first_name'),
                    'last_name' => $this->getPostParam('last_name'),
                    'phone' => $this->getPostParam('phone'),
                    'address' => $this->getPostParam('address'),
                    'email' => $this->getPostParam('email', FILTER_VALIDATE_EMAIL)
                );
                $status = $this->getPostParam('status');
                $record['status'] = empty($status) || $status != 'on' ? 0 : 1;
                
                if (empty($record['username']) || empty($record['email'])) {
                    throw new Exception($this->text('invalid-request'));
                }
                $context['record'] = $record;
                
                $id = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
                $context['id'] = $id;
                
                $organization = $this->getPostParam('organization', FILTER_VALIDATE_INT);
                if (!empty($organization)) {
                    $this->indopost('/record?model=' . OrganizationUserModel::class, array(
                        'record' => array('organization_id' => $organization, 'account_id' => $id)
                    ));
                    $context['organization'] = $organization;
                }
                
                $file = new FileController($this->getRequest());
                $file->init("/accounts/$id");
                $file->allowExtensions((new File())->getAllowed(3));
                $photo = $file->upload('photo');
                if (isset($photo['name'])) {
                    $photo_path = $file->getPathUrl($photo['name']);
                    $payload = array(
                        'record' => array('photo' => $photo_path),
                        'condition' => array('WHERE' => "id=$id")
                    );
                    $context['photo'] = $photo_path;
                    $this->indoput('/record?model=' . Accounts::class, $payload);
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('accounts')
                ));
                
                $level = LogLevel::INFO;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
            } else {
                $template = $this->twigDashboard($this->text('accounts'));
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
                $template->render($this->twigTemplate(dirname(__FILE__) . '/account-insert.html', array('organizations' => $organizations)));
                
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            
            if ($is_submit) {
                echo $this->respondJSON(array('message' => $e->getMessage()));
            } else {
                $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
            }
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        $context = array('id' => $id, 'model' => Accounts::class);
        
        try {
            if (!$this->isUserAuthorized()
                    || (!$this->getUser()->can('system_account_update')
                    && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($id == 1 && $this->getUser()->getAccount()['id'] != $id) {
                throw new Exception('No one but root can edit this account!');
            }
            
            if ($is_submit) {
                $record = array(
                    'username' => $this->getPostParam('username'),
                    'first_name' => $this->getPostParam('first_name'),
                    'last_name' => $this->getPostParam('last_name'),
                    'phone' => $this->getPostParam('phone'),
                    'address' => $this->getPostParam('address'),
                    'email' => $this->getPostParam('email', FILTER_VALIDATE_EMAIL)
                );
                $password = $this->getPostParam('password');
                if (!empty($password)) {
                    $record['password'] = password_hash($password, PASSWORD_BCRYPT);
                }
                if ($this->getUser()->is('system_coder')) {
                    $status = $this->getPostParam('status');
                    $record['status'] = empty($status) || $status != 'on' ? 0 : 1;
                }
                $context['record'] = $record;
                $context['record']['id'] = $id;

                if (empty($record['username']) || empty($record['email'])) {
                    throw new Exception($this->text('invalid-request'));
                }
                
                $pattern = '/record?model=' . Accounts::class;
                
                $existing_username = $this->indoSafe($pattern, array('username' => $record['username']));
                if ($existing_username && $existing_username['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " username => [{$record['username']}]");
                }
                $existing_email = $this->indoSafe($pattern, array('email' => $record['email']));
                if ($existing_email && $existing_email['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " email => [{$record['email']}]");
                }
                
                $existing = $this->indoSafe($pattern, array('id' => $id));
                $old_photo_file = basename($existing['photo'] ?? '');
                if (isset($_FILES['photo'])) {
                    $file = new FileController($this->getRequest());
                    $file->init("/accounts/$id");
                    $file->allowExtensions((new File())->getAllowed(3));
                    $photo = $file->upload('photo');
                    if (isset($photo['name'])) {
                        $record['photo'] = $file->getPathUrl($photo['name']);
                    }
                } else {
                    $record['photo'] = '';
                }
                if (isset($record['photo'])) {
                    if (!empty($old_photo_file)) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/accounts/$id/$old_photo_file");
                    }
                    $context['record']['photo'] = $record['photo'];
                }
                
                $this->indoput($pattern, array('record' => $record, 'condition' => ['WHERE' => "id=$id"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('accounts')
                ));
                
                $organizations = array();
                $post_organizations = $this->getPostParam('organizations', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?? array();
                foreach ($post_organizations as $org_id) {
                    $organizations[$org_id] = true;
                }

                $org_user = $this->indo('/statement', array(
                    'query' => "SELECT id,organization_id FROM organization_users WHERE account_id=$id AND is_active=1"));
                foreach ($org_user as $row) {
                    if (isset($organizations[(int)$row['organization_id']])) {
                        unset($organizations[(int)$row['organization_id']]);
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
                
                if ($this->getUser()->can('system_rbac')) {
                    $post_roles = $this->getPostParam('roles', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?? array();
                    $roles = array();
                    foreach ($post_roles as $role) {
                        $roles[$role] = true;
                    }

                    $user_role = $this->indo('/statement', array(
                        'query' => "SELECT id,role_id FROM rbac_user_role WHERE user_id=$id AND is_active=1"));
                    foreach ($user_role as $row) {
                        if (isset($roles[(int)$row['role_id']])) {
                            unset($roles[(int)$row['role_id']]);
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
                $template = $this->twigDashboard($this->text('accounts'));
                $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id));
                $context['record'] = $record;
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
                $vars = array('record' => $record, 'organizations' => $organizations);
             
                $org_id_query =
                        'SELECT ou.organization_id as id ' .
                        'FROM organization_users as ou JOIN organizations as o ON ou.organization_id=o.id ' .
                        "WHERE ou.account_id=$id AND ou.is_active=1 AND o.is_active=1";
                $org_ids = $this->indo('/statement', array('query' => $org_id_query));
                $ids = array();
                foreach ($org_ids as $org) {
                    $ids[] = $org['id'];
                }
                $vars['current_organizations'] = implode(',', $ids);
                
                $rbacs = array('common' => 'Common');            
                $alias_names = $this->indo('/statement', array(
                    'query' => "SELECT alias,name FROM organizations WHERE alias!='common' AND is_active=1 ORDER By id desc"));
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
                
                $template->render($this->twigTemplate(dirname(__FILE__) . '/account-update.html', $vars));
                
                $level = LogLevel::NOTICE;
                $context['current_role'] = $vars['current_role'];
                $context['current_organizations'] = $vars['current_organizations'];
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ";
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                echo $this->respondJSON(array('message' => $e->getMessage()));
            } else {
                $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        $context = array('id' => $id, 'model' => Accounts::class);
        
        try {            
            if (!$this->isUserAuthorized()
                    || (!$this->getUser()->can('system_account_index')
                    && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $template = $this->twigDashboard($this->text('accounts'));
            $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id));
            $context['record'] = $record;
            
            $organizations_query =
                    'SELECT t2.name ' .
                    'FROM organization_users as t1 JOIN organizations as t2 ON t1.organization_id=t2.id ' .
                    "WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=$id";
            $organizations = $this->indo('/statement', array('query' => $organizations_query));

            $user_role_query =
                    'SELECT CONCAT(t2.alias, "_", t2.name) as name ' . 
                    'FROM rbac_user_role as t1 JOIN rbac_roles as t2 ON t1.role_id=t2.id ' .
                    "WHERE t1.is_active=1 AND t1.user_id=$id";
            $user_roles = $this->indo('/statement', array('query' => $user_role_query));
            
            $template->render($this->twigTemplate(dirname(__FILE__) . '/account-view.html', array(
                'record' => $record, 'roles' => $user_roles, 'organizations' => $organizations, 'accounts' => $this->getAccounts()
            )));

            $level = LogLevel::NOTICE;
            $message = "{$record['username']} хэрэглэгчийн мэдээллийг нээж үзэж байна";
            $context += array('roles' => $user_roles, 'organizations' => $organizations);
        } catch (Throwable $e) {
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            
            $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        $context = array('model' => Accounts::class);
        
        try {
            if (!$this->isUserCan('system_account_delete')) {
                throw new Exception('No permission for an action [delete]!');
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                    || !isset($payload['name'])
                    || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $context['payload'] = $payload;
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }
            
            $this->indodelete("/record?{$table}model=" . Accounts::class, array('WHERE' => "id='{$payload['id']}'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ));
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function requestsModal($table)
    {
        $context = array();
        
        try {
            if (!$this->isUserCan('system_account_index')) {
                throw new Exception($this->text('system-no-permission'));
            }

            if (!in_array($table, array('forgot', 'newbie'))) {
                throw new Exception($this->text('invalid-request'));
            }
            
            $modal = dirname(__FILE__) . "/$table-index-modal.html";
            if (!file_exists($modal)) {
                throw new Exception("$modal file not found!");
            }

            if ($table == 'forgot') {
                $modelName = ForgotModel::class;
                $message = 'Нууц үгээ сэргээх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            } else {
                $modelName = Accounts::class;
                $message = 'Шинэ хэрэглэгчээр бүртгүүлэх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            }
            $context += array('model' => $modelName, 'table' => $table);
            $vars = array(
                'rows' => $this->indo("/record/rows?table=$table&model=$modelName", array('WHERE' => 'is_active!=999'))
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
        } catch (Throwable $e) {
            echo $this->errorNoPermissionModal($e->getMessage());

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчдийн мэдээллийн хүснэгт нээж үзэх хүсэлт алдаатай байна';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function requestApprove()
    {
        $context = array('reason' => 'account-request-approve', 'model' => Accounts::class);
        
        try {
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception('No permission for an action [approval]!');
            }
            
            $id = $this->getParsedBody()['id'] ?? null;            
            if (empty($id)
                    || !filter_var($id, FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $context['id'] = $id;
            
            $record = $this->indo('/record?table=newbie&model=' . Accounts::class, array('id' => $id));
            $existing = $this->indo('/statement', array(
                'query' => 'SELECT id FROM rbac_accounts WHERE username=:username OR email=:email',
                'bind' => array(
                    ':email' => array('var' => $record['email']),
                    ':username' => array('var' => $record['username'])
                )
            ));
            if (!empty($existing)) {
                throw new Exception($this->text('account-exists') . "<br/>username/email => {$record['username']}/{$record['email']}");
            }
            
            $organization_name = $record['address'] ?? null;
            if (!empty($organization_name)) {
                $organization = $this->indo('/statement', array(
                    'query' => 'SELECT id FROM organizations WHERE name=:name AND is_active=1 LIMIT 1',
                    'bind' => array(':name' => array('var' => $organization_name))
                ));
                if (!empty($organization)) {
                    $organization_id = current($organization)['id'];
                }
            }
            
            $record['address'] = '';
            unset($record['id']);
            unset($record['is_active']);
            unset($record['created_at']);
            unset($record['created_by']);
            unset($record['updated_at']);
            unset($record['updated_by']);
            $account_id = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
            $context['account'] = $record;
            $context['account']['id'] = $account_id;

            $payload = array(
                'condition' => array('WHERE' => "id=$id"),
                'record' => array('is_active' => 0, 'status' => 2)
            );
            $this->indoput('/record?table=newbie&model=' . Accounts::class, $payload);
            
            if (isset($organization_id)) {
                $this->indopost('/record?model=' . OrganizationUserModel::class,
                        array('record' => array('account_id' => $account_id, 'organization_id' => $organization_id)));
                $context['organization'] = $organization_id;
            }

            $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $lookup = $this->indo('/lookup', array('table' => 'templates', 'condition' =>
                array('WHERE' => "c.code='$code' AND p.keyword='approve-new-account' AND p.is_active=1")));
            if (isset($lookup['approve-new-account'])) {
                $content = $lookup['approve-new-account'];
                
                $template = new MemoryTemplate();
                $template->source($content['full'][$code]);
                $template->set('email', $record['email']);
                $template->set('login', $this->generateLink('login', [], true));
                $template->set('username', $record['username']);
                $this->indo('/send/stmp/email', array(
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
            ));

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function requestDelete()
    {
        $context = array('reason' => 'account-request-approve', 'model' => Accounts::class, 'table' => 'newbie');
        
        try {
            if (!$this->isUserCan('system_account_delete')) {
                throw new Exception('No permission for an action [delete]!');
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                    || !isset($payload['name'])
                    || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $context['payload'] = $payload;
            
            $this->indodelete("/record?table=newbie&model=" . Accounts::class, array('WHERE' => "id='{$payload['id']}'"));
            
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
            ));
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
}
