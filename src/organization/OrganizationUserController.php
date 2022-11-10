<?php

namespace Raptor\Organization;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\RBAC\Accounts;

use Indoraptor\Account\OrganizationModel;
use Indoraptor\Account\OrganizationUserModel;

use Raptor\Dashboard\DashboardController;

class OrganizationUserController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['organization'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['organization'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new Exception($this->text('system-no-permission'));
            }

            $user_orgs_query =
                'SELECT t2.* FROM organization_users as t1 JOIN organizations as t2 ON t1.organization_id=t2.id ' .
                'WHERE t1.is_active=1 AND t2.is_active=1 AND t1.account_id=' . $this->getUser()->getAccount()['id'];
            $organizations = $this->indo('/statement', array('query' => $user_orgs_query));

            $this->twigDashboard(dirname(__FILE__) . '/organization-user.html', array('organizations' => $organizations))->render();
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгч өөрийн байгууллагуудын жагсаалтыг нээж үзэж байна';
        } catch (Throwable $e) {
            $level = LogLevel::ERROR;
            $message = $e->getMessage();
            
            $this->dashboardProhibited($message)->render();
        } finally {
            $this->indolog('account', $level, $message);
        }        
    }
    
    public function set(int $account_id)
    {
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        $context = array('reason' => 'organization-user-set', 'account_id' => $account_id);
        
        try {
            if (!$this->isUserCan('system_account_organization_set')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($is_submit) {
                $organizations = array();
                $post_organizations = filter_var($this->getParsedBody()['organizations'] ?? array(), FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $id) {
                    $organizations[$id] = true;
                }
                if ($account_id == 1
                    && (empty($organizations) || !array_key_exists(1, $organizations))
                ) {
                    throw new Exception('Default user must belong to an organization');
                }

                $user_orgs = $this->indo('/statement', array(
                    'query' => "SELECT id,organization_id FROM organization_users WHERE account_id=$account_id AND is_active=1"));
                foreach ($user_orgs as $row) {
                    if (isset($organizations[(int)$row['organization_id']])) {
                        unset($organizations[(int)$row['organization_id']]);
                    } else {
                        $this->indodelete('/record?model=' . OrganizationUserModel::class, array('WHERE' => "id={$row['id']}"));
                        $this->indolog(
                            'account',
                            LogLevel::ALERT,
                            "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс $account_id дугаар бүхий хэрэглэгчийг хаслаа",
                            array('reason' => 'organization-strip', 'account_id' => $account_id, 'organization_id' => $row['organization_id'])
                        );
                    }
                }

                foreach (array_keys($organizations) as $id) {
                    $record_id = $this->indopost('/record?model=' . OrganizationUserModel::class,
                        array('record' => array('account_id' => $account_id, 'organization_id' => $id)));
                    $this->indolog(
                        'account',
                        LogLevel::ALERT,
                        "$account_id дугаартай хэрэглэгчийг $id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                        array('reason' => 'organization-set', 'account_id' => $account_id, 'organization_id' => $id, 'record_id' => $record_id)
                    );
                }

                return $this->respondJSON(array(
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success'),
                    'href'    => $this->generateLink('accounts')
                ));
            } else {
                $query =
                    'SELECT ou.organization_id as id ' .
                    'FROM organization_users as ou JOIN organizations as o ON ou.organization_id=o.id ' .
                    "WHERE ou.account_id=$account_id AND ou.is_active=1 AND o.is_active=1";
                $response = $this->indo('/statement', array('query' => $query));
                $ids = array();
                foreach ($response as $org) {
                    $ids[] = $org['id'];
                }
                $current_organizations = implode(',', $ids);

                $account = $this->indo('/record?model=' . Accounts::class, array('id' => $account_id));
                $vars = array(
                    'account' => $account,
                    'current_organizations' => $current_organizations,
                    'organizations' => $this->indo('/record/rows?model=' . OrganizationModel::class),
                );
                
                $template_path = dirname(__FILE__) . '/organization-user-set-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!");
                }
                $this->twigTemplate($template_path, $vars)->render();

                $context['account'] = $account;
                $context['current_organizations'] = $current_organizations;
                $this->indolog(
                    'account',
                    LogLevel::NOTICE,
                    "$account_id дугаартай хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг эхлүүллээ",
                    $context
                );
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
            $this->indolog(
                'account',
                LogLevel::ERROR,
                "$account_id дугаартай хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо",
                $context
            );
        }
    }
}
