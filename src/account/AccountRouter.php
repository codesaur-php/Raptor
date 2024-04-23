<?php

namespace Raptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    public function __construct()
    {
        $this->GET('/accounts', [AccountController::class, 'index'])->name('accounts');
        $this->GET('/accounts/list', [AccountController::class, 'list'])->name('accounts-list');
        $this->GET_POST('/accounts/insert', [AccountController::class, 'insert'])->name('account-insert');
        $this->GET_PUT('/accounts/update/{uint:id}', [AccountController::class, 'update'])->name('account-update');
        $this->GET('/accounts/view/{uint:id}', [AccountController::class, 'view'])->name('account-view');
        $this->DELETE('/accounts/delete', [AccountController::class, 'delete'])->name('account-delete');
        
        $this->GET('/accounts/requests/{table}/modal', [AccountController::class, 'requestsModal'])->name('accounts-requests-modal');
        $this->POST('/accounts/request/approve', [AccountController::class, 'requestApprove'])->name('account-request-approve');
        $this->DELETE('/accounts/request/delete', [AccountController::class, 'requestDelete'])->name('account-request-delete');
    }
}
