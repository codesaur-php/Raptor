<?php

namespace Raptor\Account;

use Raptor\Dashboard\DashboardController;

class AccountController extends DashboardController
{    
    public function index()
    {
        $template = $this->twigDashboard($this->text('accounts'));
        if (!$this->getUser()->can('system_account_index')) {
            return $template->alertErrorPermission();
        }
        $template->render($this->twigTemplate(dirname(__FILE__) . '/account-index.html'));
    }
}
