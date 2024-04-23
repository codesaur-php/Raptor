<?php

namespace Raptor\Organization;

use codesaur\Router\Router;

class OrganizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/organizations', [OrganizationController::class, 'index'])->name('organizations');
        $this->GET('/organizations/list', [OrganizationController::class, 'list'])->name('organizations-list');
        $this->GET_POST('/organizations/insert', [OrganizationController::class, 'insert'])->name('organization-insert');
        $this->GET_PUT('/organizations/update/{uint:id}', [OrganizationController::class, 'update'])->name('organization-update');
        $this->GET('/organizations/view/{uint:id}', [OrganizationController::class, 'view'])->name('organization-view');
        $this->DELETE('/organizations/delete', [OrganizationController::class, 'delete'])->name('organization-delete');

        $this->GET('/organization/user/list', [OrganizationUserController::class, 'index'])->name('organization-user');
        $this->GET_POST('/organization/user/set', [OrganizationUserController::class, 'set'])->name('organization-user-set');
    }
}
