<?php

namespace Raptor\Account;

use PDO;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;

use Indoraptor\Account\OrganizationModel;
use Indoraptor\Account\OrganizationUserModel;

use Raptor\Dashboard\DashboardController;

class OrganizationUserController extends DashboardController
{    
    public function set(int $account_id)
    {
        if ($this->getRequest()->getMethod() == 'POST') {
            if (!$this->isUserCan('system_account_organization_set')) {
                return $this->respondJSON(array(
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $this->text('system-no-permission')
                ));
            }
            $organizations = array();
            $post_organizations = $this->getPostParam('organizations', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?? array();
            foreach ($post_organizations as $id) {
                $organizations[$id] = true;
            }
            
            $org_user = $this->indo('/statement', array(
                'bind' => array(':id' => array('var' => $account_id, 'type' => PDO::PARAM_INT)),
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
                                    "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $account_id дугаар бүхий хэрэглэгчийг хаслаа.",
                                    array('reason' => 'organization-strip', 'account_id' => $account_id, 'organization_id' => $row['organization_id'])
                            );
                        }

                    }
                }
            }

            foreach (array_keys($organizations) as $id) {
                $org_set = $this->indopost('/record?model=' . OrganizationUserModel::class,
                        array('record' => array('account_id' => $account_id, 'organization_id' => $id)));                
                if (isset($org_set['id'])) {
                    $this->indolog(
                            'account',
                            LogLevel::ALERT,
                            "$account_id дугаартай хэрэглэгчийг $id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ.",
                            array('reason' => 'organization-set', 'account_id' => $account_id, 'organization_id' => $id)
                    );
                }
            }
            
            return $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-update-success'),
                'href'    => $this->generateLink('accounts')
            ));
        } elseif (!$this->isUserCan('system_account_organization_set')) {
            die($this->errorNoPermissionModal());
        } else {
            $sql =  'SELECT ou.organization_id as id ' .
                    'FROM organization_users as ou JOIN organizations as o ON ou.organization_id=o.id ' .
                    'WHERE ou.account_id=:id AND ou.is_active=1 AND o.is_active=1';
            $response = $this->indo('/statement', array('query' => $sql,
                'bind' => array(':id' => array('var' => $account_id, 'type' => PDO::PARAM_INT))));
            if (!isset($response['error']['code'])
                    && !empty($response)
            ) {
                $ids = array();
                foreach ($response as $org) {
                    $ids[] = $org['id'];
                }
                $current_organizations = implode(',', $ids);
            } else {
                $current_organizations = null;
            }
            
            $account = $this->indo('/record?model=' . Accounts::class, array('id' => $account_id))['record'] ?? array();
            $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class)['rows'] ?? array();
            $vars = array(
                'account' => $account,
                'organizations' => $organizations,
                'current_organizations' => $current_organizations
            );
            $this->twigTemplate(dirname(__FILE__) . '/account-organization-set-modal.html', $vars)->render();
            
            $this->indolog(
                    'account',
                    LogLevel::NOTICE,
                    "$account_id дугаартай хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг эхлүүллээ.",
                    array('reason' => 'organization-set', 'account' => $account, 'organizations' => $current_organizations)
            );
        }
    }
}
