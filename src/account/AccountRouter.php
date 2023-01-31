<?php

namespace Raptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    public function __construct()
    {
        $this->GET('/accounts', [AccountController::class, 'index'])->name('accounts');
        $this->GET_POST('/account/insert', [AccountController::class, 'insert'])->name('account-insert');
        $this->GET_PUT('/account/update/{uint:id}', [AccountController::class, 'update'])->name('account-update');
        $this->GET('/account/view/{uint:id}', [AccountController::class, 'view'])->name('account-view');
        $this->DELETE('/account/delete', [AccountController::class, 'delete'])->name('account-delete');
        
        $this->GET('/accounts/requests/{table}/modal', [AccountController::class, 'requestsModal'])->name('accounts-requests-modal');
        $this->POST('/account/request/approve', [AccountController::class, 'requestApprove'])->name('account-request-approve');
        $this->DELETE('/account/request/delete', [AccountController::class, 'requestDelete'])->name('account-request-delete');
    }
}
