<?php

namespace Raptor\Authentication;

interface UserInterface
{
    public function is(string $role): bool;
    public function can(string $permission, ?string $role = null): bool;

    public function getToken(): string;
    public function getAccount(): array;
    public function getAlias(): string;
    public function getOrganization(): array;
    public function getOrganizations(): array;
    public function getRBAC(): array;
}
