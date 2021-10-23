<?php

namespace Raptor\Authentication;

interface RBACUserInterface
{
    public function is($role): bool;
    public function can($permission, $role = null): bool;

    public function getAccount();
    public function getOrganization();
    public function getOrganizations();
}
