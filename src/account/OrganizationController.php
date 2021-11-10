<?php

namespace Raptor\Account;

use Exception;

use Indoraptor\Account\OrganizationModel;

use Raptor\Dashboard\DashboardController;

class OrganizationController extends DashboardController
{    
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                    || !$this->getUser()->can('system_org_retrieve')
            ) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $record = $this->indo('/record?model=' . OrganizationModel::class, array('id' => $id))['record'] ?? array();
            if (!empty($record['parent_id'])) {
                $record['parent_name'] = $this->indo('/record?model=' . OrganizationModel::class, array('id' => $record['parent_id']))['record']['name'] ?? '';
            }
                
            $this->twigTemplate(dirname(__FILE__) . '/organization-retrieve-modal.html', array('record' => $record, 'accounts' => $this->getAccounts()))->render();
            // TODO: Baiguullagiin medeelel neej ehelsen esvel uzsen log bichih
        } catch (Exception $e) {
            echo $this->errorNoPermissionModal($e->getMessage());
            // TODO: aldaanii log bichih            
        }
    }
}
