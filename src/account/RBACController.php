<?php

namespace Raptor\Account;

use Exception;
use Throwable;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;

use Raptor\Dashboard\DashboardController;

class RBACController extends DashboardController
{
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new Exception($this->text('system-no-permission'));
            }
            $values = array('role' => $this->getQueryParam('role'));
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
            $this->twigTemplate($template_path, $values)->render();
        } catch (Throwable $e) {
            echo $this->errorNoPermissionModal($e->getMessage());
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
                $organizations_query = "SELECT alias,name FROM organizations WHERE alias!='common' AND is_active=1 ORDER By id desc";
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
                echo $this->errorNoPermissionModal($e->getMessage());
            }
            
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $this->indolog('rbac', LogLevel::ERROR, "$id дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо", $context);
        }
    }
}
