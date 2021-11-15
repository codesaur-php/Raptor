<?php

namespace Raptor\Account;

use PDO;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;
use codesaur\RBAC\UserRole;

use Raptor\Dashboard\DashboardController;

class RBACController extends DashboardController
{
    public function viewRole()
    {
        if (!$this->isUserCan('system_rbac')) {
            die($this->errorNoPermissionModal());
        }
        
        $values = array('role' => $this->getQueryParam('role'));
        $role_result = $this->indo('/statement', array(
            'bind' => array(':role' => array('var' => $values['role'])),
            'query' => "SELECT description FROM rbac_roles WHERE CONCAT_WS('_',alias,name)=:role AND is_active=1 ORDER By id desc LIMIT 1"));
        if (isset($role_result[0]['description'])) {
            $values['description'] = $role_result[0]['description'];
        }        
        $this->twigTemplate(dirname(__FILE__) . '/rbac-view-role-modal.html', $values)->render();
    }
    
    public function setUserRole(int $id)
    {
        if ($this->getRequest()->getMethod() == 'POST') {
            if (!$this->isUserCan('system_rbac')) {
                return $this->respondJSON(array(
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $this->text('system-no-permission')
                ));
            }
            
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
            
            return $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-update-success'),
                'href'    => $this->generateLink('accounts')
            ));
        } elseif (!$this->isUserCan('system_rbac')) {
            die($this->errorNoPermissionModal());
        } else {
            $vars = array('account' => $this->indo('/record?model=' . Accounts::class, array('id' => $id))['record'] ?? array());

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
            
            $this->twigTemplate(dirname(__FILE__) . '/rbac-user-role-modal.html', $vars)->render();
            
            $this->indolog(
                    'rbac',
                    LogLevel::NOTICE,
                    "$id дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг эхлүүллээ.",
                    array('reason' => 'rbac-user-role', 'account_id' => $id)
            );
        }
    }
}
