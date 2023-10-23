<?php

namespace Raptor\RBAC;

use Psr\Log\LogLevel;

use codesaur\RBAC\Roles;
use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;
use codesaur\RBAC\Permissions;
use codesaur\RBAC\RolePermission;

use Raptor\Dashboard\DashboardController;

class RBACController extends DashboardController
{
    public function alias()
    {
        try {
            $level = LogLevel::NOTICE;
            $queryParams = $this->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            $title = $queryParams['title'] ?? null;
            $context = ['alias' => $alias];
            
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = ['bind' => [':alias' => ['var' => $alias]]];
            $payload['query'] = "SELECT id,name,description FROM rbac_roles WHERE alias=:alias AND is_active=1 AND !(name='coder' AND alias='system')";
            $roles = $this->indo('/execute/fetch/all', $payload);
            
            $payload['query'] = 'SELECT id,name,description FROM rbac_permissions WHERE alias=:alias AND is_active=1 ORDER By module';
            $permissions = $this->indo('/execute/fetch/all', $payload);
            
            if ($alias == 'system'
                && empty($permissions)
            ) {
                $nowdate = \date('Y-m-d H:i:s');
                $query =
                    "INSERT INTO rbac_permissions(created_at,alias,module,name,description) "
                    . "VALUES('$nowdate','system','log','logger',''),"
                    . "('$nowdate','system','account','rbac',''),"
                    . "('$nowdate','system','account','account_index',''),"
                    . "('$nowdate','system','account','account_insert',''),"
                    . "('$nowdate','system','account','account_update',''),"
                    . "('$nowdate','system','account','account_delete',''),"
                    . "('$nowdate','system','organization','organization_index',''),"
                    . "('$nowdate','system','organization','organization_insert',''),"
                    . "('$nowdate','system','organization','organization_update',''),"
                    . "('$nowdate','system','organization','organization_delete',''),"
                    . "('$nowdate','system','content','content_settings',''),"
                    . "('$nowdate','system','content','content_index',''),"
                    . "('$nowdate','system','content','content_insert',''),"
                    . "('$nowdate','system','content','content_update',''),"
                    . "('$nowdate','system','content','content_delete',''),"
                    . "('$nowdate','system','localization','localization_index',''),"
                    . "('$nowdate','system','localization','localization_insert',''),"
                    . "('$nowdate','system','localization','localization_update',''),"
                    . "('$nowdate','system','localization','localization_delete','')";
                $this->indo('/execute/fetch/all', ['query' => $query]);
                $permissions = $this->indo('/execute/fetch/all', $payload);
            }
            
            $role_permission = [];
            $payload['query'] = 'SELECT role_id,permission_id FROM rbac_role_permission WHERE alias=:alias AND is_active=1';
            $rp = $this->indo('/execute/fetch/all', $payload);
            foreach ($rp ?? [] as $row) {
                $role_permission[$row['role_id']][$row['permission_id']] = true;
            }
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/rbac-alias.html',
                [
                    'alias' => $alias, 
                    'title' => $title, 
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'role_permission' => $role_permission
                ]
            );
            $dashboard->set('title', "RBAC - $alias");
            $dashboard->render();
            
            $message = "RBAC [$alias] жагсаалтыг нээж үзэж байна";
        } catch (\Throwable $e) {
            $message = 'RBAC жагсаалтыг нээх үед алдаа гарлаа. ' . $e->getMessage();
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $this->indolog('rbac', $level, $message, $context);
        }
    }
    
    public function insertRole(string $alias)
    {
        try {
            $title = $this->getQueryParams()['title'] ?? '';
            $context = ['reason' => 'rbac-insert-role'];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($is_submit) {
                $id = $this->indopost('/record?model=' . Roles::class, $payload + ['alias' => $alias]);
                $context['id'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('rbac-alias') . '?alias=' . \urlencode($alias) . '&title=' . \urlencode($this->getQueryParams()['title'] ?? '')
                ]);
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/rbac-insert-role-modal.html', ['alias' => $alias, 'title' => $title])->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC дүр шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $values = ['role' => $this->getQueryParams()['role'] ?? ''];
            $role_result = $this->indo('/execute/fetch/all', [
                'bind' => [':role' => ['var' => $values['role']]],
                'query' => "SELECT id,description FROM rbac_roles WHERE CONCAT_WS('_',alias,name)=:role AND is_active=1 ORDER By id desc LIMIT 1"
            ]);
            if (empty($role_result)) {
                throw new \Exception($this->text('record-not-found'), 404);
            }
            $values += \current($role_result);
            $this->twigTemplate(\dirname(__FILE__) . '/rbac-view-role-modal.html', $values)->render();
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
        }
    }
    
    public function insertPermission(string $alias)
    {
        try {
            $title = $this->getQueryParams()['title'] ?? '';
            $context = ['reason' => 'rbac-insert-permission'];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($is_submit) {
                $id = $this->indopost('/record?model=' . Permissions::class, $payload + ['alias' => $alias]);
                $context['id'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('rbac-alias') . '?alias=' . \urlencode($alias) . '&title=' . \urlencode($title)
                ]);
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/rbac-insert-permission-modal.html', ['alias' => $alias, 'title' => $title])->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function setRolePermission(string $alias)
    {
        // TODO: please write action log!
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($alias)
                || empty($payload['role_id'])
                || empty($payload['permission_id'])
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $payload['alias'] = $alias;
            
            $result = $this->indo('/execute/fetch/all', [
                'query' => 'SELECT id FROM rbac_role_permission WHERE alias=:alias AND role_id=:role AND permission_id=:permission AND is_active=1',
                'bind' => [
                    ':alias' => ['var' => $payload['alias']],
                    ':role' => ['var' => $payload['role_id']],
                    ':permission' => ['var' => $payload['permission_id']]
                ]
            ]);
            if ($this->getRequest()->getMethod() == 'POST') {
                if (empty($result)) {
                    $response = $this->indopost('/record?model=' . RolePermission::class, $payload);
                    return $this->respondJSON(['type' => 'success', 'title' => $this->text('success'), 'message' => $this->text('record-insert-success')]);
                }
            } else {
                $id = \reset($result)['id'] ?? null;
                if (!empty($id)
                    && \filter_var($id, \FILTER_VALIDATE_INT) !== false
                ) {
                    $response = $this->indodelete('/record?model=' . RolePermission::class, ['WHERE' => "id=$id"]);
                    return $this->respondJSON(['type' => 'primary', 'message' => $this->text('record-successfully-deleted')]);
                }
            }
            throw new \Exception($this->text('invalid-values'), 400);
        } catch (\Throwable $e) {
            $this->respondJSON([
                'type' => 'error',
                'title' => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }
    
    public function setUserRole(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['reason' => 'rbac-set-user-role', 'id' => $id];
            
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $account = $this->indoget('/record?model=' . Accounts::class, ['id' => $id]);
            $context['account'] = $account;
            
            if ($is_submit) {
                $post_roles = \filter_var($this->getParsedBody()['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                $roles = [];
                foreach ($post_roles as $role) {
                    $roles[$role] = true;
                }
                if ((empty($roles) || !\array_key_exists(1, $roles)) && $id == 1) {
                    throw new \Exception('Default user must have a role', 403);
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
                            "$id дугаартай {$account['username']} хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
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
                        "$id дугаартай  {$account['username']} хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                        ['reason' => 'role-set', 'account_id' => $id, 'role_id' => $role_id]
                    );
                }

                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success'),
                    'href'    => $this->generateLink('accounts')
                ]);
            } else {
                $vars = ['account' => $this->indoget('/record?model=' . Accounts::class, ['id' => $id])];

                $rbacs = ['common' => 'Common'];
                $organizations_query = "SELECT alias,name FROM indo_organizations WHERE alias!='common' AND is_active=1 ORDER By id desc";
                $organizations_result = $this->indo('/execute/fetch/all', ['query' => $organizations_query]);
                foreach ($organizations_result as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $roles_query = 'SELECT id,alias,name,description FROM rbac_roles WHERE is_active=1';
                $roles_result = $this->indo('/execute/fetch/all', ['query' => $roles_query]);
                \array_walk($roles_result, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $current_role = [];
                $current_role_query =
                    'SELECT rur.role_id FROM rbac_user_role as rur INNER JOIN rbac_roles as rr ON rur.role_id=rr.id ' .
                    "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->indo('/execute/fetch/all', ['query' => $current_role_query]);
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = \implode(',', $current_role);
                $this->twigTemplate(\dirname(__FILE__) . '/rbac-set-user-role-modal.html', $vars)->render();

                $this->indolog('rbac', LogLevel::NOTICE, "$id дугаартай  {$account['username']} хэрэглэгчийн дүрийг тохируулах үйлдлийг эхлүүллээ", $context);
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog('rbac', LogLevel::ERROR, "$id дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо", $context);
        }
    }
}
