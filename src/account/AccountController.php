<?php

namespace Raptor\Account;

use Exception;
use DateTime;
use PDO;

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
        if (!$this->isUserCan('system_account_index')) {
            return $template->alertNoPermission();
        }
        
        $code = $this->getLanguageCode();
        $accounts = $this->indo('/record/rows?model=' . Accounts::class)['rows'] ?? array();
        $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class)['rows'] ?? array();
        $statuses = $this->indo('/lookup', array('table' => 'status', 'condition' => array('WHERE' => "c.code='$code' AND p.is_active=1")));
        
        $org_users_query = 'SELECT t1.account_id, t1.organization_id ' .
                'FROM organization_users as t1 JOIN organizations as t2 ON t1.organization_id=t2.id ' .
                'WHERE t1.is_active=1 AND t2.is_active=1';
        $org_users_result = $this->indo('/statement', array('query' => $org_users_query));
        $org_users = isset($org_users_result['error']['code']) ? [] : $org_users_result;
        array_walk($org_users, function($value) use (&$accounts) {
            if (isset($accounts[$value['account_id']])) {
                if (!isset($accounts[$value['account_id']]['organizations'])) {
                    $accounts[$value['account_id']]['organizations'] = array();
                }
                $accounts[$value['account_id']]['organizations'][] = $value['organization_id'];
            }
        });

        $user_role_query = 'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                'FROM rbac_user_role as t1 JOIN rbac_roles as t2 ON t1.role_id=t2.id WHERE t1.is_active=1';
        $user_role_result = $this->indo('/statement', array('query' => $user_role_query));
        $user_role = isset($user_role_result['error']['code']) ? [] : $user_role_result;
        array_walk($user_role, function($value) use (&$accounts) {
            if (isset($accounts[$value['user_id']])) {
                if (!isset($accounts[$value['user_id']]['roles'])) {
                    $accounts[$value['user_id']]['roles'] = array();
                }
                $accounts[$value['user_id']]['roles'][] = "{$value['alias']}_{$value['name']}";
            }
        });
        
        $template->render($this->twigTemplate(dirname(__FILE__) . '/account-index.html', array(
            'accounts' => $accounts, 'statuses' => $statuses, 'organizations' => $organizations
        )));
        
        // TODO: Account jagsaalt uzsen log bichih
    }
    
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($this->getRequest()->getMethod() == 'POST') {
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
                
                if (empty($record['username'] || empty($record['email']))) {
                    throw new Exception($this->text('invalid-request'));
                }
                
                $response = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
                if (!isset($response['id'])) {
                    throw new Exception($response['error']['message'] ?? $this->text('invalid-request'));
                }
                
                $organization = $this->getPostParam('organization', FILTER_VALIDATE_INT);
                if (!empty($organization)) {
                    $this->indopost('/record?model=' . OrganizationUserModel::class, array(
                        'record' => array('organization_id' => $organization, 'account_id' => $response['id'])
                    ));
                }
                
                $file = new FileController($this->getRequest());
                $file->init("/accounts/{$response['id']}");
                $file->allowExtensions((new File())->getAllowed(3));
                $photo = $file->upload('photo');
                if (isset($photo['name'])) {
                    $photo_path = $file->getPathUrl($photo['name']);
                    $payload = array(
                        'record' => array('photo' => $photo_path),
                        'condition' => array('WHERE' => "id={$response['id']}")
                    );
                    $this->indoput('/record?model=' . Accounts::class, $payload);
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('accounts')
                ));
            } else {
                $template = $this->twigDashboard($this->text('accounts'));
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class)['rows'] ?? array();
                $template->render($this->twigTemplate(dirname(__FILE__) . '/account-insert.html', array('organizations' => $organizations)));
            }
            // TODO: Account uusgej ehelsen esvel uusgesen log bichih
        } catch (Exception $e) {
            // TODO: aldaanii log bichih
            
            if ($this->getRequest()->getMethod() == 'POST') {
                return $this->respondJSON(array('message' => $e->getMessage()));
            }
            
            return $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
        }
    }
    
    public function update(int $id)
    {
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
            
            if ($this->getRequest()->getMethod() == 'POST') {
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
                
                if (empty($record['username'] || empty($record['email']))) {
                    throw new Exception($this->text('invalid-request'));
                }
                
                $existing = $this->indo('/record?model=' . Accounts::class, array('username' => $record['username']))['record'] ?? array();
                if (isset($existing['id']) && $existing['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " username => [{$record['username']}]");
                }
                $existing_email = $this->indo('/record?model=' . Accounts::class, array('email' => $record['email']))['record'] ?? array();
                if (isset($existing_email['id']) && $existing_email['id'] != $id) {
                    throw new Exception($this->text('account-exists') . " email => [{$record['email']}]");
                }                
                if (isset($_FILES['photo'])) {
                    $file = new FileController($this->getRequest());
                    $file->init("/accounts/$id");
                    $file->allowExtensions((new File())->getAllowed(3));
                    $photo = $file->upload('photo');
                    if (isset($photo['name'])) {
                        $record['photo'] = $file->getPathUrl($photo['name']);
                    }
                } else {
                    // TODO: account photo-g frontendees ustgahiig hussen gej uzen bichleg arilgalaa
                    // umnuh bichlegt ni photo zaagdsan baisan bol bodit file ni servert hadgalaatai baisaar baigaa buguud file_delete hiih eseh talaar bodie!
                    $record['photo'] = '';
                }
                
                $response = $this->indoput('/record?model=' . Accounts::class,
                        array('record' => $record, 'condition' => ['WHERE' => "id=$id"]));
                if (!isset($response['id'])) {
                    throw new Exception($response['error']['message'] ?? $this->text('invalid-request'));
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('accounts')
                ));
                
                if ($this->getUser()->can('system_account_organization_set')) {
                    $organizations = array();
                    $post_organizations = $this->getPostParam('organizations', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?? array();
                    foreach ($post_organizations as $org_id) {
                        $organizations[$org_id] = true;
                    }

                    $org_user = $this->indo('/statement', array(
                        'bind' => array(':id' => array('var' => $id, 'type' => PDO::PARAM_INT)),
                        'query' => 'SELECT id,organization_id FROM organization_users WHERE account_id=:id AND is_active=1'));
                    if (!isset($org_user['error']['code'])
                            && !empty($org_user)
                    ) {
                        foreach ($org_user as $row) {
                            if (isset($organizations[(int)$row['organization_id']])) {
                                unset($organizations[(int)$row['organization_id']]);
                            } else {
                                $org_delete = $this->indodelete('/record?model=' . OrganizationUserModel::class, array('WHERE' => "id={$row['id']}"));
                                if (!empty($org_delete['id'])) {
                                    $this->indolog(
                                            'account',
                                            LogLevel::ALERT,
                                            "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $id дугаар бүхий хэрэглэгчийг хаслаа.",
                                            array('reason' => 'organization-strip', 'account_id' => $id, 'organization_id' => $row['organization_id'])
                                    );
                                }

                            }
                        }
                    }

                    foreach (array_keys($organizations) as $org_id) {
                        $org_set = $this->indopost('/record?model=' . OrganizationUserModel::class,
                                array('record' => array('account_id' => $id, 'organization_id' => $org_id)));                
                        if (isset($org_set['id'])) {
                            $this->indolog(
                                    'account',
                                    LogLevel::ALERT,
                                    "$id дугаартай хэрэглэгчийг $org_id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ.",
                                    array('reason' => 'organization-set', 'account_id' => $id, 'organization_id' => $org_id)
                            );
                        }
                    }
                }
                
                if ($this->getUser()->can('system_rbac')) {
                    $post_roles = $this->getPostParam('roles', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?? array();
                    $roles = array();
                    foreach ($post_roles as $role) {
                        $roles[$role] = true;
                    }

                    $user_role = $this->indo('/statement', array(
                        'bind' => array(':user_id' => array('var' => $id, 'type' => PDO::PARAM_INT)),
                        'query' => 'SELECT id,role_id FROM rbac_user_role WHERE user_id=:user_id AND is_active=1'));
                    if (!isset($user_role['error']['code'])
                            && !empty($user_role)
                    ) {
                        foreach ($user_role as $row) {
                            if (isset($roles[(int)$row['role_id']])) {
                                unset($roles[(int)$row['role_id']]);
                            } else {
                                $user_role_delete = $this->indodelete('/record?model=' . UserRole::class, array('WHERE' => "id={$row['id']}"));
                                if (!empty($user_role_delete['id'])) {
                                    $this->indolog(
                                            'rbac',
                                            LogLevel::ALERT,
                                            "$id дугаартай хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа..",
                                            array('reason' => 'role-strip', 'account_id' => $id, 'role_id' => $row['role_id'])
                                    );
                                }

                            }
                        }
                    }
                    foreach (array_keys($roles) as $role_id) {
                        $user_role_set = $this->indopost('/record?model=' . UserRole::class,
                                array('record' => array('user_id' => $id, 'role_id' => $role_id)));                
                        if (isset($user_role_set['id'])) {
                            $this->indolog(
                                    'rbac',
                                    LogLevel::ALERT,
                                    "$id дугаартай хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ.",
                                    array('reason' => 'role-set', 'account_id' => $id, 'role_id' => $role_id)
                            );
                        }
                    }
                }
            } else {
                $template = $this->twigDashboard($this->text('accounts'));
                $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id))['record'] ?? array();
                $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class)['rows'] ?? array();
                $vars = array('record' => $record, 'organizations' => $organizations);
             
                $sql =  'SELECT ou.organization_id as id ' .
                        'FROM organization_users as ou JOIN organizations as o ON ou.organization_id=o.id ' .
                        'WHERE ou.account_id=:id AND ou.is_active=1 AND o.is_active=1';
                $response = $this->indo('/statement', array('query' => $sql,
                    'bind' => array(':id' => array('var' => $id, 'type' => PDO::PARAM_INT))));
                if (!isset($response['error']['code'])
                        && !empty($response)
                ) {
                    $ids = array();
                    foreach ($response as $org) {
                        $ids[] = $org['id'];
                    }
                    $vars['current_organizations'] = implode(',', $ids);
                } else {
                    $vars['current_organizations'] = null;
                }
                
                $rbacs = array('common' => 'Common');            
                $organizations_query = "SELECT alias,name FROM organizations WHERE alias!='common' AND is_active=1 ORDER By id desc";
                $organizations_result = $this->indo('/statement', array('query' => $organizations_query));
                if (!isset($organizations_result['error']['code'])
                        && !empty($organizations_result)
                ) {
                    foreach ($organizations_result as $row) {
                        if (isset($rbacs[$row['alias']])) {
                            $rbacs[$row['alias']] .= ", {$row['name']}";
                        } else {
                            $rbacs[$row['alias']] = $row['name'];
                        }
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = array_map(function() { return array(); }, array_flip(array_keys($rbacs)));
                $roles_query = 'SELECT id,alias,name,description FROM rbac_roles WHERE is_active=1';
                $roles_result = $this->indo('/statement', array('query' => $roles_query));
                if (!isset($roles_result['error']['code'])
                        && !empty($roles_result)
                ) {
                    array_walk($roles_result, function($value) use (&$roles) {
                        if ( ! isset($roles[$value['alias']])) {
                            $roles[$value['alias']] = array();
                        }
                        $roles[$value['alias']][$value['id']] = array($value['name']);

                        if ( ! empty($value['description'])) {
                            $roles[$value['alias']][$value['id']][] = $value['description'];
                        }
                    });
                }
                $vars['roles'] = $roles;

                $current_roles = array();
                $current_role_query = 'SELECT rur.role_id FROM rbac_user_role as rur INNER JOIN rbac_roles as rr ON rur.role_id=rr.id ' .
                                      "WHERE rur.user_id=:user_id AND rur.is_active=1 AND rr.is_active=1";
                $current_role_result = $this->indo('/statement', array(
                    'query' => $current_role_query, 'bind' => array(':user_id' => array('var' => $id, 'type' => PDO::PARAM_INT))));
                if (!isset($current_role_result['error']['code'])
                        && !empty($current_role_result)
                ) {
                    foreach ($current_role_result as $row) {
                        $current_roles[] = $row['role_id'];
                    }
                }
                $vars['current_role'] = implode(',', $current_roles);
                
                $template->render($this->twigTemplate(dirname(__FILE__) . '/account-update.html', $vars));
            }
            // TODO: Account update ehelsen esvel update log bichih
        } catch (Exception $e) {
            // TODO: aldaanii log bichih
            
            if ($this->getRequest()->getMethod() == 'POST') {
                return $this->respondJSON(array('message' => $e->getMessage()));
            }
            
            return $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                    || (!$this->getUser()->can('system_account_retrieve')
                    && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $template = $this->twigDashboard($this->text('accounts'));
            $record = $this->indo('/record?model=' . Accounts::class, array('id' => $id))['record'] ?? array();
            $organizations_query = 'SELECT t2.name ' .
                    'FROM organization_users as t1 JOIN organizations as t2 ON t1.organization_id=t2.id ' .
                    "WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=$id";
            $organizations_result = $this->indo('/statement', array('query' => $organizations_query));
            $organizations = isset($organizations_result['error']['code']) ? [] : $organizations_result;

            $user_role_query = 'SELECT CONCAT(t2.alias, "_", t2.name) as name ' . 
                    'FROM rbac_user_role as t1 JOIN rbac_roles as t2 ON t1.role_id=t2.id ' .
                    "WHERE t1.is_active=1 AND t1.user_id=$id";
            $user_role_result = $this->indo('/statement', array('query' => $user_role_query));
            $user_roles = isset($user_role_result['error']['code']) ? [] : $user_role_result;
            
            $template->render($this->twigTemplate(dirname(__FILE__) . '/account-view.html', array(
                'record' => $record, 'roles' => $user_roles, 'organizations' => $organizations, 'accounts' => $this->getAccounts()
            )));
            // TODO: Account neej ehelsen esvel uzsen log bichih
        } catch (Exception $e) {
            // TODO: aldaanii log bichih
            
            if ($this->getRequest()->getMethod() == 'POST') {
                return $this->respondJSON(array('message' => $e->getMessage()));
            }
            
            return $this->twigDashboard($this->text('accounts'))->alertNoPermission($e->getMessage());
        }
    }
    
    public function approve()
    {
        try {
            if (!$this->isUserCan('system_account_insert')) {
                throw new Exception('No permission for an action [approval]!');
            }
            
            $id = $this->getParsedBody()['id'] ?? null;            
            if (empty($id)) {
                throw new Exception($this->text('invalid-request'));
            }
            
            $record = $this->indo('/record?table=newbie&model=' . Accounts::class, array('id' => $id))['record'] ?? [];
            if (empty($record)) {
                throw new Exception($this->text('invalid-values'));
            }
            
            $username_or_email = "username='{$record['username']}' OR email='{$record['email']}'";                        
            $account = $this->indo('/record/rows?model=' . Accounts::class, array('WHERE' => $username_or_email));
            if (!empty($account['rows'])) {
                throw new Exception($this->text('account-exists') . "<br/>username/email => {$record['username']}/{$record['email']}");
            }
            
            $address = $record['address'] ?? null;            
            if (!empty($address)) {
                $org = $this->indo('/record?model=' . OrganizationModel::class, array('name' => $address, 'is_active' => 1));
                if (!empty($org['record'])) {
                    $organization = $org['record'];
                    if (isset($organization['id'])) {
                        $organization_id = $organization['id'];
                    }
                }
            }
            
            $record['address'] = '';
            unset($record['id']);
            unset($record['is_active']);
            unset($record['created_at']);
            unset($record['created_by']);
            unset($record['updated_at']);
            unset($record['updated_by']);
            $result = $this->indopost('/record?model=' . Accounts::class, array('record' => $record));
            if (!isset($result['id'])) {
                throw new Exception($this->text('account-insert-error'));
            }

            $this->indolog(
                    'account',
                    LogLevel::ALERT,
                    "Шинэ бүртгүүлсэн {$record['username']} нэртэй [{$record['email']}] хаягтай хэрэглэгчийн хүсэлтийг зөвшөөрч системд нэмлээ.",
                    array('reason' => 'approve-new-account', 'account' => $record)
            );

            $payload = array(
                'condition' => array('WHERE' => "id=$id"),
                'record' => array('is_active' => 0, 'status' => 2)
            );
            $this->indoput('/record?table=newbie&model=' . Accounts::class, $payload);
            
            if (isset($organization_id)) {
                $this->indopost('/record?model=' . OrganizationUserModel::class,
                        array('record' => array('account_id' => $result['id'], 'organization_id' => $organization_id)));
            }

            $code = $this->getLanguageCode();
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
                'message' => $this->text('account-insert-success')
            ));
        } catch (Exception $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ));
        } finally {
            // TODO: Account zuvshuursun log bichih
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_account_delete')) {
                throw new Exception('No permission for an action [delete]!');
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                    || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }
            
            $record = $this->indodelete("/record?{$table}model=" . Accounts::class, array('WHERE' => "id='{$payload['id']}'"));
            if (empty($record['id'])) {
                throw new Exception($this->text('invalid-values'));
            }
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
        } catch (Exception $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ));
        } finally {
            // TODO: delete log write! $payload['table'] yalgaj ali husnegtees ustgasniig temdegleh
        }
    }
    
    public function requestsModal($table)
    {
        $modal = dirname(__FILE__) . "/$table-index-modal.html";
        if (!file_exists($modal)
                || !$this->isUserCan('system_account_index')
                || !in_array($table, array('forgot', 'newbie'))
        ) {
            die($this->errorNoPermissionModal());
        }
        
        $modelName = ($table == 'forgot' ? ForgotModel::class : Accounts::class);
        $vars = array(
            'rows' => $this->indo("/record/rows?table=$table&model=$modelName", array('WHERE' => 'is_active!=999'))['rows'] ?? []
        );        
            
        $template = $this->twigTemplate($modal, $vars);
        $template->addFunction(new TwigFunction('isExpired', function ($date, $minutes = 5): bool
        {
            $now_date = new DateTime();
            $then = new DateTime($date);
            $diff = $then->diff($now_date);
            return $diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > $minutes;
        }));
        
        // TODO: Account burtguuleh huseltuud bolon, nuuts ug solih huseltuudiin jagsaaltiig neej bui log bichih
        
        return $template->render();
    }
}
