<?php

namespace Raptor\Organization;

use codesaur\Router\Router;

class OrganizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/organization/user/list', [OrganizationUserController::class, 'index'])->name('organization-user');
        $this->GET_POST('/organization/user/set/{uint:account_id}', [OrganizationUserController::class, 'set'])->name('organization-user-set');
        
        $this->GET('/organizations', [OrganizationController::class, 'index'])->name('organizations');
        $this->GET('/organizations/datatable', [OrganizationController::class, 'datatable'])->name('organizations-datatable');
        $this->GET_POST('/organizations/organization/insert', [OrganizationController::class, 'insert'])->name('organization-insert');
        $this->GET_PUT('/organizations/organization/update/{uint:id}', [OrganizationController::class, 'update'])->name('organization-update');
        $this->GET('/organizations/organization/view/{uint:id}', [OrganizationController::class, 'view'])->name('organization-view');
        $this->DELETE('/organizations/organization/delete', [OrganizationController::class, 'delete'])->name('organization-delete');
    }
}
