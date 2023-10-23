<?php

namespace Raptor\Account;

use Twig\TwigFunction;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;
use codesaur\Template\MemoryTemplate;

use Indoraptor\Auth\OrganizationModel;
use Indoraptor\Auth\OrganizationUserModel;

use Raptor\Authentication\ForgotModel;
use Raptor\Authentication\AccountRequestsModel;
use Raptor\File\PrivateFilesController;
use Raptor\Dashboard\DashboardController;

class AccountController extends DashboardController
{    
    public function index()
    {
        try {
            $context = [];
                
            if (!$this->isUserCan('system_account_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $accounts = $this->indoget('/records?model=' . Accounts::class);
            $organizations = $this->indoget('/records?model=' . OrganizationModel::class);
            
            $org_users_query =
                'SELECT t1.account_id, t1.organization_id ' .
                'FROM indo_organization_users as t1 INNER JOIN indo_organizations as t2 ON t1.organization_id=t2.id ' .
                'WHERE t1.is_active=1 AND t2.is_active=1';
            $org_users = $this->indo('/execute/fetch/all', ['query' => $org_users_query]);
            \array_walk($org_users, function($value) use (&$accounts) {
                if (isset($accounts[$value['account_id']])) {
                    if (!isset($accounts[$value['account_id']]['organizations'])) {
                        $accounts[$value['account_id']]['organizations'] = [];
                    }
                    $accounts[$value['account_id']]['organizations'][] = $value['organization_id'];
                }
            });
            
            $user_role_query =
                'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                'FROM rbac_user_role as t1 INNER JOIN rbac_roles as t2 ON t1.role_id=t2.id WHERE t1.is_active=1';
            $user_role = $this->indo('/execute/fetch/all', ['query' => $user_role_query]);
            \array_walk($user_role, function($value) use (&$accounts) {
                if (isset($accounts[$value['user_id']])) {
                    if (!isset($accounts[$value['user_id']]['roles'])) {
                        $accounts[$value['user_id']]['roles'] = [];
                    }
                    $accounts[$value['user_id']]['roles'][] = "{$value['alias']}_{$value['name']}";
                }
            });
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/account-index.html',
                ['accounts' => $accounts, 'organizations' => $organizations]
            );
            $dashboard->set('title', $this->text('accounts'));
            $dashboard->render();
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэх үед алдаа гарлаа';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            
            $this->dashboardProhibited("$message.<br/><br/>{$e->getMessage()}", $e->getCode())->render();
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function insert()
    {
        try {
            $context = [];
            
            if (!$this->isUserCan('system_account_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($this->getRequest()->getMethod() == 'POST') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL)
                ];
                if (empty($parsedBody['password'])) {
                    $bytes = \random_bytes(10);
                    $password = \bin2hex($bytes);
                } else {
                    $password = $parsedBody['password'];
                }
                $record['password'] = \password_hash($password, \PASSWORD_BCRYPT);
                
                $pattern = '/record?model=' . Accounts::class;
                
                $status = $parsedBody['status'] ?? 'off';
                $record['status'] = $status != 'on' ? 0 : 1;
                $id = $this->indopost($pattern, $record);
                
                if (!empty($parsedBody['organization'] ?? null)) {
                    $organization = \filter_var($parsedBody['organization'], \FILTER_VALIDATE_INT);
                    if ($organization !== false) {
                        try {
                            $this->indo('/record?model=' . OrganizationModel::class, ['id' => $organization]);
                            $this->indopost('/record?model=' . OrganizationUserModel::class, ['organization_id' => $organization, 'account_id' => $id]);
                            $context += ['organization' => $organization];
                        } catch (\Throwable $e) {
                        }
                    }
                }
                
                $file = new PrivateFilesController($this->getRequest());
                $file->setFolder("/accounts/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo');
                if ($photo) {
                    $payload = [
                        'record' => ['photo' => $photo['path']],
                        'condition' => ['WHERE' => "id=$id"]
                    ];
                    $this->indoput($pattern, $payload);
                    $context += ['photo' => $photo];
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('accounts')
                ]);
                
                $level = LogLevel::INFO;
                $context += ['id' => $id, 'record' => $record];
                $message = 'Хэрэглэгч үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
            } else {
                $organizations = $this->indoget('/records?model=' . OrganizationModel::class);
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/account-insert.html',
                    ['organizations' => $organizations]
                );
                $dashboard->set('title', $this->text('new-account'));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function update(int $id)
    {
        try {
            $context = [];
            
            if (!$this->isUserAuthorized()
                || (!$this->getUser()->can('system_account_update')
                    && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($id == 1 && $this->getUser()->getAccount()['id'] != $id) {
                throw new \Exception('No one but root can edit this account!', 403);
            }
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }

                $record = [
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL)
                ];
                if (!empty($parsedBody['password'])) {
                    $record['password'] = \password_hash($parsedBody['password'], \PASSWORD_BCRYPT);
                }
                
                $status = $parsedBody['status'] ?? 'off';
                $record['status'] = $status != 'on' ? 0 : 1;
                if ($id == 1 && $record['status'] == 0) {
                    // u can't deactivate root account!
                    unset($record['status']);
                }
                
                $context = ['record' => $record + ['id' => $id]];
                
                $pattern = '/record?model=' . Accounts::class;
                try {
                    $existing_username = $this->indo($pattern, ['username' => $record['username']]);
                } catch (\Throwable $e) {
                }
                
                try {
                    $existing_email = $this->indo($pattern, ['email' => $record['email']]);
                } catch (\Throwable $e) {
                }
                
                if (!empty($existing_username) && $existing_username['id'] != $id) {
                    throw new \Exception($this->text('account-exists') . " username => [{$record['username']}]", 403);
                } elseif (!empty($existing_email) && $existing_email['id'] != $id) {
                    throw new \Exception($this->text('account-exists') . " email => [{$record['email']}]", 403);
                }
                
                try {
                    $current_record = $this->indo($pattern, ['id' => $id]);
                    if (empty($current_record['photo'])) {
                        throw new \Exception('Current record had no photo!');
                    }
                    $current_photo_file = \basename($current_record['photo']);
                } catch (\Throwable $e) {
                    $current_photo_file = null;
                }
                
                $file = new PrivateFilesController($this->getRequest());
                $file->setFolder("/accounts/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo');
                if ($photo) {
                    $record['photo'] = $photo['path'];
                }

                if (!empty($current_photo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_photo_file);
                        $record['photo'] = '';
                    } elseif (!empty($record['photo'])
                        && \basename($record['photo']) != $current_photo_file
                    ) {
                        $file->tryDeleteFile($current_photo_file);
                    }
                }
                
                if (isset($record['photo'])) {
                    $context['photo'] = $record['photo'];
                }
                
                $this->indoput($pattern, ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]);
                
                $this->respondJSON([ 
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('accounts')
                ]);
                
                $organizations = [];
                $post_organizations = \filter_var($parsedBody['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $org_id) {
                    $organizations[$org_id] = true;
                }
                
                if ($this->isUserCan('system_account_organization_set') && !empty($organizations)) {
                    $org_user = $this->indo('/execute/fetch/all', [
                        'query' => "SELECT id,organization_id FROM indo_organization_users WHERE account_id=$id AND is_active=1"
                    ]);
                    foreach ($org_user as $row) {
                        if (isset($organizations[(int) $row['organization_id']])) {
                            unset($organizations[(int) $row['organization_id']]);
                        } elseif ($row['organization_id'] == 1 && $id == 1) {
                            // can't strip root account from system organization!
                        } else {
                            $this->indodelete(
                                '/record?model=' . OrganizationUserModel::class,
                                ['WHERE' => "id={$row['id']}"]
                            );
                            $this->indolog(
                                'account',
                                LogLevel::ALERT,
                                "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $id дугаар бүхий хэрэглэгчийг хаслаа",
                                ['reason' => 'organization-strip', 'account_id' => $id, 'organization_id' => $row['organization_id']]
                            );
                        }
                    }

                    foreach (\array_keys($organizations) as $org_id) {
                        $this->indopost(
                            '/record?model=' . OrganizationUserModel::class,
                            ['account_id' => $id, 'organization_id' => $org_id]
                        );
                        $this->indolog(
                            'account',
                            LogLevel::ALERT,
                            "$id дугаартай хэрэглэгчийг $org_id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                            ['reason' => 'organization-set', 'account_id' => $id, 'organization_id' => $org_id]
                        );
                    }
                }
                
                if ($this->isUserCan('system_rbac')) {
                    $post_roles = \filter_var($parsedBody['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                    $roles = [];
                    foreach ($post_roles as $role) {
                        $roles[$role] = true;
                    }

                    $user_role = $this->indo('/execute/fetch/all', [
                        'query' => "SELECT id,role_id FROM rbac_user_role WHERE user_id=$id AND is_active=1"
                    ]);
                    foreach ($user_role as $row) {
                        if (isset($roles[(int) $row['role_id']])) {
                            unset($roles[(int) $row['role_id']]);
                        } elseif ($row['role_id'] == 1 && $id == 1) {
                            // can't delete root account's coder role!
                        } elseif ($row['role_id'] == 1 && !$this->getUser()->is('system_coder')) {
                            // only coder can strip another coder role
                        } else {
                            $this->indodelete('/record?model=' . UserRole::class, ['WHERE' => "id={$row['id']}"]);
                            
                            $this->indolog(
                                'rbac',
                                LogLevel::ALERT,
                                "$id дугаартай хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
                                ['reason' => 'role-strip', 'account_id' => $id, 'role_id' => $row['role_id']]
                            );
                        }
                    }
                    
                    foreach (\array_keys($roles) as $role_id) {
                        if ($role_id == 1 && (
                            !$this->getUser()->is('system_coder') || $this->getUser()->getAccount()['id'] != 1)
                        ) {
                            // only root coder can add another coder role
                            continue;
                        }
                        $this->indopost(
                            '/record?model=' . UserRole::class,
                            ['user_id' => $id, 'role_id' => $role_id]
                        );
                        $this->indolog(
                            'rbac',
                            LogLevel::ALERT,
                            "$id дугаартай хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                            ['reason' => 'role-set', 'account_id' => $id, 'role_id' => $role_id]
                        );
                    }
                }
                
                $level = LogLevel::INFO;
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget('/record?model=' . Accounts::class, ['id' => $id]);
                $organizations = $this->indoget('/records?model=' . OrganizationModel::class);
                $vars = ['record' => $record, 'organizations' => $organizations];
             
                $org_id_query =
                    'SELECT ou.organization_id as id ' .
                    'FROM indo_organization_users as ou INNER JOIN indo_organizations as o ON ou.organization_id=o.id ' .
                    "WHERE ou.account_id=$id AND ou.is_active=1 AND o.is_active=1";
                $org_ids = $this->indo('/execute/fetch/all', ['query' => $org_id_query]);
                $ids = [];
                foreach ($org_ids as $org) {
                    $ids[] = $org['id'];
                }
                $vars['current_organizations'] = \implode(',', $ids);
                
                $rbacs = ['common' => 'Common'];
                $alias_names = $this->indo('/execute/fetch/all', [
                    'query' => "SELECT alias,name FROM indo_organizations WHERE alias!='common' AND is_active=1 ORDER By id desc"
                ]);
                foreach ($alias_names as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $rbac_roles = $this->indo('/execute/fetch/all', [
                    'query' => 'SELECT id,alias,name,description FROM rbac_roles WHERE is_active=1'
                ]);
                \array_walk($rbac_roles, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $current_role_query =
                    'SELECT rur.role_id FROM rbac_user_role as rur INNER JOIN rbac_roles as rr ON rur.role_id=rr.id ' .
                    "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->indo('/execute/fetch/all', ['query' => $current_role_query]);
                $current_role = [];
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = \implode(',', $current_role);
                
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/account-update.html', $vars);
                $dashboard->set('title', $this->text('edit-account'));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $context += [
                    'record' => $record,
                    'current_role' => $vars['current_role'],
                    'current_organizations' => $vars['current_organizations']
                ];
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                || (!$this->getUser()->can('system_account_index')
                && $this->getUser()->getAccount()['id'] != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . Accounts::class, ['id' => $id]);
            
            $organizations_query =
                'SELECT t2.name ' .
                'FROM indo_organization_users as t1 INNER JOIN indo_organizations as t2 ON t1.organization_id=t2.id ' .
                "WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=$id";
            $organizations = $this->indo('/execute/fetch/all', ['query' => $organizations_query]);

            $user_role_query =
                'SELECT CONCAT(t2.alias, "_", t2.name) as name ' . 
                'FROM rbac_user_role as t1 INNER JOIN rbac_roles as t2 ON t1.role_id=t2.id ' .
                "WHERE t1.is_active=1 AND t1.user_id=$id";
            $user_roles = $this->indo('/execute/fetch/all', ['query' => $user_role_query]);
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/account-view.html',
                [
                    'record' => $record, 'roles' => $user_roles,
                    'organizations' => $organizations, 'accounts' => $this->getAccounts()
                ]
            );
            $dashboard->set('title', $this->text('account'));
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['username']} хэрэглэгчийн мэдээллийг нээж үзэж байна";
            $context = ['record' => $record, 'roles' => $user_roles, 'organizations' => $organizations];
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_account_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            $context = ['payload' => $payload];
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            if ($this->getUser()->getAccount()['id'] == $id) {
                throw new \Exception('Cannot suicide myself :(', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first acccount!', 403);
            }
            
            $this->indodelete('/record?model=' . Accounts::class, ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function requestsModal(string $table)
    {
        try {
            if (!$this->isUserCan('system_account_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if (!\in_array($table, ['forgot', 'newbie'])) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            if ($table == 'forgot') {
                $modelName = ForgotModel::class;
                $message = 'Нууц үгээ сэргээх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            } else {
                $modelName = AccountRequestsModel::class;
                $message = 'Шинэ хэрэглэгчээр бүртгүүлэх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            }
            $vars = [
                'rows' => $this->indoget("/records?model=$modelName", ['WHERE' => 'is_active!=999'])
            ];
            
            $template = $this->twigTemplate(\dirname(__FILE__) . "/$table-index-modal.html", $vars);
            $template->addFunction(new TwigFunction('isExpired', function (string $date, int $minutes = 5): bool
            {
                $now_date = new \DateTime();
                $then = new \DateTime($date);
                $diff = $then->diff($now_date);
                return $diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > $minutes;
            }));
            $template->render();
            
            $level = LogLevel::NOTICE;
            $context = ['model' => $modelName, 'table' => $table];
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = "Хэрэглэгчдийн мэдээллийн хүснэгт [$table] нээж үзэх хүсэлт алдаатай байна";
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context);
        }
    }
    
    public function requestApprove()
    {
        try {
            $context = ['reason' => 'account-request-approve'];
            
            if (!$this->isUserCan('system_account_insert')) {
                throw new \Exception('No permission for an action [approval]!', 401);
            }
            
            $parsedBody = $this->getParsedBody();
            $id = $parsedBody['id'] ?? null;
            if (empty($id)
                || !\filter_var($id, \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context += ['payload' => $parsedBody, 'id' => $id];
            
            $record = $this->indoget('/record?model=' . AccountRequestsModel::class, ['id' => $id]);
            $existing = $this->indo('/execute/fetch/all', [
                'query' => 'SELECT id FROM rbac_accounts WHERE username=:username OR email=:email',
                'bind' => [
                    ':email' => ['var' => $record['email']],
                    ':username' => ['var' => $record['username']]
                ]
            ]);
            if (!empty($existing)) {
                throw new \Exception($this->text('account-exists') . ": username/email => {$record['username']}/{$record['email']}", 403);
            }
            
            unset($record['id']);
            unset($record['status']);
            unset($record['is_active']);
            unset($record['created_at']);
            unset($record['created_by']);
            unset($record['updated_at']);
            unset($record['updated_by']);
            unset($record['rbac_account_id']);
            $account_id = $this->indopost('/record?model=' . Accounts::class, $record);
            $context += ['account' => $record + ['id' => $account_id]];

            $payload = [
                'condition' => ['WHERE' => "id=$id"],
                'record' => ['is_active' => 0, 'status' => 2, 'rbac_account_id' => $account_id]
            ];
            $this->indoput('/record?model=' . AccountRequestsModel::class, $payload);
            
            $organization_id = \filter_var($parsedBody['organization_id'] ?? 0, \FILTER_VALIDATE_INT);
            if (!$organization_id) {
                $organization_id = 1;
            }
            
            try {
                $this->indo(
                    '/record/insert?model=' . OrganizationUserModel::class,
                    ['account_id' => $account_id, 'organization_id' => $organization_id]
                );
                $context += ['organization' => $organization_id];
            } catch (\Throwable $e) {
                $this->errorLog($e);
            }
            
            try {
                $code = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
                $reference = $this->indo(
                    '/reference/templates',
                    ['WHERE' => "c.code='$code' AND p.keyword='approve-new-account' AND p.is_active=1"]
                );
                $content = $reference['approve-new-account'];
                $template = new MemoryTemplate();
                $template->source($content['full'][$code]);
                $template->set('email', $record['email']);
                $template->set('login', $this->generateLink('login', [], true));
                $template->set('username', $record['username']);
                $this->indo(
                    '/send/mail',
                    [
                        'to' => $record['email'],
                        'message' => $template->output(),
                        'subject' => $content['title'][$code]
                    ]
                );
            } catch (\Throwable $e) {
                $this->errorLog($e);
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-insert-success')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "Шинэ бүртгүүлсэн {$record['username']} нэртэй [{$record['email']}] хаягтай хэрэглэгчийн хүсэлтийг зөвшөөрч системд нэмлээ";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
    
    public function requestDelete()
    {
        try {
            $context = ['reason' => 'account-request-delete', 'table' => 'newbie'];
            
            if (!$this->isUserCan('system_account_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context += ['payload' => $payload];
            
            $this->indodelete('/record?model=' . AccountRequestsModel::class, ['WHERE' => "id='{$payload['id']}'"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчээр бүртгүүлэх хүсэлтийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('account', $level, $message, $context + ['model' => Accounts::class]);
        }
    }
}
