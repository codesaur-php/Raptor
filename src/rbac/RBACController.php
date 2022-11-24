<?php

namespace Raptor\RBAC;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\RBAC\Roles;
use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;
use codesaur\RBAC\Permissions;
use codesaur\RBAC\RolePermission;

use Raptor\Dashboard\DashboardController;

class RBACController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])) {
            $queryParams = $request->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            $meta['content']['title'][$localization['code']] = "RBAC - $alias";
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function alias()
    {
        try {
            $level = LogLevel::NOTICE;
            $queryParams = $this->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            $title = $queryParams['title'] ?? null;
            $context = array('alias' => $alias);
            
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $payload = array('bind' => array(':alias' => array('var' => $alias)));            
            $payload['query'] = "SELECT id,name,description FROM rbac_roles WHERE alias=:alias AND is_active=1 AND (alias!='system' AND name!='coder')";
            $roles = $this->indo('/statement', $payload);
            
            $payload['query'] = 'SELECT id,name,description FROM rbac_permissions WHERE alias=:alias AND is_active=1 ORDER By module';            
            $permissions = $this->indo('/statement', $payload);            
            
            $role_permission = array();
            $payload['query'] = 'SELECT role_id,permission_id FROM rbac_role_permission WHERE alias=:alias AND is_active=1';
            $rp = $this->indo('/statement', $payload);
            foreach ($rp ?? array() as $row) {
                $role_permission[$row['role_id']][$row['permission_id']] = true;
            }
            $this->twigDashboard(
                dirname(__FILE__) . '/rbac-alias.html',
                array(
                    'alias' => $alias, 
                    'title' => $title, 
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'role_permission' => $role_permission
                )
            )->render();
            $message = "RBAC [$alias] жагсаалтыг нээж үзэж байна";
        } catch (Throwable $e) {
            $message = 'RBAC жагсаалтыг нээх үед алдаа гарлаа. ' . $e->getMessage();
            $this->dashboardProhibited($e->getMessage())->render();
        } finally {
            $this->indolog('rbac', $level, $message, $context);
        }
    }
    
    public function insertRole(string $alias)
    {
        $title = $this->getQueryParams()['title'] ?? '';
        $context = array('reason' => 'rbac-insert-role');
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }

            if ($is_submit) {
                $id = $this->indopost('/record?model=' . Roles::class, array('record' => $payload + array('alias' => $alias)));
                $context['id'] = $id;
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('rbac-alias') . '?alias=' . urlencode($alias) . '&title=' . urlencode($this->getQueryParams()['title'] ?? '')
                ));
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $this->twigTemplate(dirname(__FILE__) . '/rbac-insert-role-modal.html', array('alias' => $alias, 'title' => $title))->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (Exception $e) {
            if ($is_submit) {
                $this->respondJSON(array(
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ));
            } else {
                $this->modalProhibited($e->getMessage())->render();
            }
            
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC дүр шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }
            $values = array('role' => $this->getQueryParams()['role'] ?? '');
            $role_result = $this->indo('/statement', array(
                'bind' => array(':role' => array('var' => $values['role'])),
                'query' => "SELECT id,description FROM rbac_roles WHERE CONCAT_WS('_',alias,name)=:role AND is_active=1 ORDER By id desc LIMIT 1"));
            if (empty($role_result)) {
                throw new Exception($this->text('record-not-found'));
            }
            $values += current($role_result);
            
            $template_path = dirname(__FILE__) . '/rbac-view-role-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!");
            }
            $this->twigDashboard($template_path, $values)->render();
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage())->render();
        }
    }
    
    public function insertPermission(string $alias)
    {
        $title = $this->getQueryParams()['title'] ?? '';
        $context = array('reason' => 'rbac-insert-permission');
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }

            if ($is_submit) {
                $id = $this->indopost('/record?model=' . Permissions::class, array('record' => $payload + array('alias' => $alias)));
                $context['id'] = $id;
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('rbac-alias') . '?alias=' . urlencode($alias) . '&title=' . urlencode($title)
                ));
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $this->twigTemplate(dirname(__FILE__) . '/rbac-insert-permission-modal.html', array('alias' => $alias, 'title' => $title))->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (Exception $e) {
            if ($is_submit) {
                $this->respondJSON(array(
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ));
            } else {
                $this->modalProhibited($e->getMessage())->render();
            }
            
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function setRolePermission(string $alias)
    {
        // TODO: please write action log!
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $payload = $this->getParsedBody();        
            if (empty($alias)
                || empty($payload['role_id'])
                || empty($payload['permission_id'])
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $payload['alias'] = $alias;
            
            $result = $this->indo('/statement', array(
                'query' => 'SELECT id FROM rbac_role_permission WHERE alias=:alias AND role_id=:role AND permission_id=:permission AND is_active=1',
                'bind' => array(
                    ':alias' => array('var' => $payload['alias']),
                    ':role' => array('var' => $payload['role_id']),
                    ':permission' => array('var' => $payload['permission_id'])
                )
            ));
            if ($this->getRequest()->getMethod() == 'POST') {
                if (empty($result)) {
                    $response = $this->indopost('/record?model=' . RolePermission::class, array('record' => $payload));
                    return $this->respondJSON(array('type' => 'success', 'title' => $this->text('success'), 'message' => $this->text('record-insert-success')));
                }
            } else {
                $id = reset($result)['id'] ?? null;
                if (!empty($id)
                    && filter_var($id, FILTER_VALIDATE_INT) !== false
                ) {
                    $response = $this->indodelete('/record?model=' . RolePermission::class, array('WHERE' => "id=$id"));
                    return $this->respondJSON(array('type' => 'primary', 'message' => $this->text('record-successfully-deleted')));
                }
            }
            throw new Exception($this->text('invalid-values'));
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'type' => 'error',
                'title' => $this->text('error'),
                'message' => $e->getMessage()
            ));
        }
    }
    
    public function setUserRole(int $id)
    {
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        $context = array('reason' => 'rbac-set-user-role', 'id' => $id);

        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $account = $this->indo('/record?model=' . Accounts::class, array('id' => $id));
            $context['account'] = $account;
            
            if ($is_submit) {
                $post_roles = filter_var($this->getParsedBody()['roles'] ?? array(), FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
                $roles = array();
                foreach ($post_roles as $role) {
                    $roles[$role] = true;
                }
                if ((empty($roles) || !array_key_exists(1, $roles)) && $id == 1) {
                    throw new Exception('Default user must have a role');
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
                            "$id дугаартай {$account['username']} хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
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
                        "$id дугаартай  {$account['username']} хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                        array('reason' => 'role-set', 'account_id' => $id, 'role_id' => $role_id)
                    );
                }

                return $this->respondJSON(array(
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success'),
                    'href'    => $this->generateLink('accounts')
                ));
            } else {
                $vars = array('account' => $this->indo('/record?model=' . Accounts::class, array('id' => $id)));

                $rbacs = array('common' => 'Common');            
                $organizations_query = "SELECT alias,name FROM indo_organizations WHERE alias!='common' AND is_active=1 ORDER By id desc";
                $organizations_result = $this->indo('/statement', array('query' => $organizations_query));
                foreach ($organizations_result as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = array_map(function() { return array(); }, array_flip(array_keys($rbacs)));
                $roles_query = 'SELECT id,alias,name,description FROM rbac_roles WHERE is_active=1';
                $roles_result = $this->indo('/statement', array('query' => $roles_query));
                array_walk($roles_result, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = array();
                    }
                    $roles[$value['alias']][$value['id']] = array($value['name']);

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $current_role = array();
                $current_role_query =
                    'SELECT rur.role_id FROM rbac_user_role as rur INNER JOIN rbac_roles as rr ON rur.role_id=rr.id ' .
                    "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->indo('/statement', array('query' => $current_role_query));
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = implode(',', $current_role);
                
                $template_path = dirname(__FILE__) . '/rbac-set-user-role-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!");
                }
                $this->twigTemplate($template_path, $vars)->render();

                $this->indolog('rbac', LogLevel::NOTICE, "$id дугаартай  {$account['username']} хэрэглэгчийн дүрийг тохируулах үйлдлийг эхлүүллээ", $context);
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array(
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $e->getMessage()
                ));
            } else {
                $this->modalProhibited($e->getMessage())->render();
            }
            
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $this->indolog('rbac', LogLevel::ERROR, "$id дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо", $context);
        }
    }
}
