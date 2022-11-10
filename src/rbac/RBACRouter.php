<?php

namespace Raptor\RBAC;

use codesaur\Router\Router;

class RBACRouter extends Router
{
    function __construct()
    {
        $this->GET('/rbac/alias', [RBACController::class, 'alias'])->name('rbac-alias');
        $this->GET_POST('/rbac/user/role/{uint:id}', [RBACController::class, 'setUserRole'])->name('rbac-set-user-role');
        $this->GET('/rbac/role/view', [RBACController::class, 'viewRole'])->name('rbac-role-view');
        $this->GET_POST('/rbac/{alias}/insert/role', [RBACController::class, 'insertRole'])->name('rbac-insert-role');
        $this->GET_POST('/rbac/{alias}/insert/permission', [RBACController::class, 'insertPermission'])->name('rbac-insert-permission');
        $this->POST_PUT('/rbac/{alias}/role/permission', [RBACController::class, 'setRolePermission'])->name('rbac-set-role-permission');
    }
}
