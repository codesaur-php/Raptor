<?php

namespace Raptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        $this->GET('/accounts', [AccountController::class, 'index'])->name('accounts');
        $this->GET('/accounts/requests/{table}/modal', [AccountController::class, 'requestsModal'])->name('accounts-requests-modal');
        $this->POST('/accounts/account/accept', [AccountController::class, 'approve'])->name('accounts-account-accept');
        $this->GET_POST('/accounts/account/insert', [AccountController::class, 'insert'])->name('accounts-account-insert');
        $this->GET_POST('/accounts/account/update/{uint:id}', [AccountController::class, 'update'])->name('accounts-account-update');
        $this->GET('/accounts/account/view/{uint:id}', [AccountController::class, 'view'])->name('accounts-account-view');
        $this->POST('/accounts/account/delete', [AccountController::class, 'delete'])->name('accounts-account-delete');

        $this->GET_POST('/accounts/account/{uint:account_id}/organization/set', [OrganizationUserController::class, 'set'])->name('accounts-organization-set');

        $this->GET('/accounts/organization/view/{uint:id}', [OrganizationController::class, 'view'])->name('accounts-organization-view');

        $this->GET_POST('/accounts/rbac/user/role/{uint:id}', [RBACController::class, 'setUserRole'])->name('accounts-rbac-user-role');
        $this->GET('/accounts/rbac/role/view', [RBACController::class, 'viewRole'])->name('accounts-rbac-role-view');
    }
}
