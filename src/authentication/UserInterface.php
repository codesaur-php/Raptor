<?php

namespace Raptor\Authentication;

interface UserInterface
{
    public function is($role): bool;
    public function can($permission, $role = null): bool;

    public function getToken();
    public function getAccount();
    public function getAlias();
    public function getOrganization();
    public function getOrganizations();
    public function getRBAC();
}
