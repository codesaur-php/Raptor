<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;

use Indoraptor\Auth\OrganizationModel;
use Indoraptor\Auth\OrganizationUserModel;

use Raptor\Dashboard\DashboardController;

class OrganizationUserController extends DashboardController
{
    public function index()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($this->isUser('system_coder')) {
                $organizations = $this->indoget('/records?model=' . OrganizationModel::class);
            } else {
                $user_orgs_query =
                    'SELECT t2.* FROM indo_organization_users as t1 INNER JOIN indo_organizations as t2 ON t1.organization_id=t2.id ' .
                    'WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=' . $this->getUser()->getAccount()['id'];
                $organizations = $this->indo('/execute/fetch/all', ['query' => $user_orgs_query]);
            }
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/organization-user.html',
                ['organizations' => $organizations]
            );
            $dashboard->set('title', $this->text('organization'));
            $dashboard->render();
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгч өөрийн байгууллагуудын жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $level = LogLevel::ERROR;
            $message = $e->getMessage();
            
            $this->dashboardProhibited($message, $e->getCode())->render();
        } finally {
            $this->indolog('account', $level, $message);
        }
    }
    
    public function set()
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['reason' => 'organization-user-set'];
            
            if (!$this->isUserCan('system_account_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            if (empty($params['account_id'])
                || \filter_var($params['account_id'], \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $account_id = (int) $params['account_id'];
            $context['account_id'] = $account_id;
            
            if ($is_submit) {
                $organizations = [];
                $post_organizations = \filter_var($this->getParsedBody()['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $id) {
                    $organizations[$id] = true;
                }
                if ($account_id == 1
                    && (empty($organizations) || !\array_key_exists(1, $organizations))
                ) {
                    throw new \Exception('Default user must belong to an organization', 503);
                }

                $user_orgs = $this->indo('/execute/fetch/all',
                    ['query' => "SELECT id,organization_id FROM indo_organization_users WHERE account_id=$account_id AND is_active=1"]);
                foreach ($user_orgs as $row) {
                    if (isset($organizations[(int) $row['organization_id']])) {
                        unset($organizations[(int) $row['organization_id']]);
                    } else {
                        $this->indodelete('/record?model=' . OrganizationUserModel::class, ['WHERE' => "id={$row['id']}"]);
                        $this->indolog(
                            'account',
                            LogLevel::ALERT,
                            "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $account_id дугаар бүхий хэрэглэгчийг хаслаа",
                            ['reason' => 'organization-strip', 'account_id' => $account_id, 'organization_id' => $row['organization_id']]
                        );
                    }
                }

                foreach (\array_keys($organizations) as $id) {
                    $record_id = $this->indopost(
                        '/record?model=' . OrganizationUserModel::class,
                        ['account_id' => $account_id, 'organization_id' => $id]);
                    $this->indolog(
                        'account',
                        LogLevel::ALERT,
                        "$account_id дугаартай хэрэглэгчийг $id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                        ['reason' => 'organization-set', 'account_id' => $account_id, 'organization_id' => $id, 'record_id' => $record_id]
                    );
                }

                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success'),
                    'href'    => $this->generateLink('accounts')
                ]);
            } else {
                $query =
                    'SELECT ou.organization_id as id ' .
                    'FROM indo_organization_users as ou INNER JOIN indo_organizations as o ON ou.organization_id=o.id ' .
                    "WHERE ou.account_id=$account_id AND ou.is_active=1 AND o.is_active=1";
                $response = $this->indo('/execute/fetch/all', ['query' => $query]);
                $current_organizations = [];
                foreach ($response as $org) {
                    $current_organizations[] = $org['id'];
                }

                $account = $this->indoget('/record?model=' . Accounts::class, ['id' => $account_id]);
                $vars = [
                    'account' => $account,
                    'current_organizations' => $current_organizations,
                    'organizations' => $this->indoget('/records?model=' . OrganizationModel::class),
                ];
                $this->twigTemplate(\dirname(__FILE__) . '/organization-user-set-modal.html', $vars)->render();

                $context['account'] = $account;
                $context['current_organizations'] = $current_organizations;
                $this->indolog(
                    'account',
                    LogLevel::NOTICE,
                    "$account_id дугаартай хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг эхлүүллээ",
                    $context
                );
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
            $this->indolog(
                'account',
                LogLevel::ERROR,
                ($account_id ?? 'үл мэдэгдэх'). ' дугаартай хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо',
                $context
            );
        }
    }
}
