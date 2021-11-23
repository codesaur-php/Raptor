<?php

namespace Raptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        $this->GET('/accounts', [AccountController::class, 'index'])->name('accounts');
        $this->GET_POST('/account/insert', [AccountController::class, 'insert'])->name('account-insert');
        $this->GET_POST('/account/update/{uint:id}', [AccountController::class, 'update'])->name('account-update');
        $this->GET('/account/view/{uint:id}', [AccountController::class, 'view'])->name('account-view');
        $this->POST('/account/delete', [AccountController::class, 'delete'])->name('account-delete');
        
        $this->GET('/accounts/requests/{table}/modal', [AccountController::class, 'requestsModal'])->name('accounts-requests-modal');
        $this->POST('/account/request/approve', [AccountController::class, 'requestApprove'])->name('account-request-approve');
        $this->POST('/account/request/delete', [AccountController::class, 'requestDelete'])->name('account-request-delete');
        
        $this->GET('/organization/user/list', [OrganizationUserController::class, 'index'])->name('organization-user');
        $this->GET_POST('/organization/user/set/{uint:account_id}', [OrganizationUserController::class, 'set'])->name('organization-user-set');
        
        $this->GET('/organizations', [OrganizationController::class, 'index'])->name('organizations');
        $this->GET('/organizations/datatable', [OrganizationController::class, 'datatable'])->name('organizations-datatable');
        $this->GET_POST('/organizations/organization/insert', [OrganizationController::class, 'insert'])->name('organization-insert');
        $this->GET_POST('/organizations/organization/update/{uint:id}', [OrganizationController::class, 'update'])->name('organization-update');
        $this->GET('/organizations/organization/view/{uint:id}', [OrganizationController::class, 'view'])->name('organization-view');
        $this->POST('/organizations/organization/delete', [OrganizationController::class, 'delete'])->name('organization-delete');

        $this->GET_POST('/rbac/user/role/{uint:id}', [RBACController::class, 'setUserRole'])->name('rbac-set-user-role');
        $this->GET('/rbac/role/view', [RBACController::class, 'viewRole'])->name('rbac-role-view');
    }
}
