<?php

namespace Raptor\Account;

use codesaur\RBAC\Accounts;

use Indoraptor\Account\OrganizationModel;

use Raptor\Dashboard\DashboardController;

class AccountController extends DashboardController
{    
    public function index()
    {
        $template = $this->twigDashboard($this->text('accounts'));
        if (!$this->getUser()->can('system_account_index')) {
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
        
        $template->render($this->twigContent(dirname(__FILE__) . '/account-index.html', array(
            'accounts' => $accounts, 'statuses' => $statuses, 'organizations' => $organizations
        )));
    }
}
