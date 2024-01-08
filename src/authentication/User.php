<?php

namespace Raptor\Authentication;

class User implements UserInterface
{
    private readonly string $_jwt;
    private readonly array $_rbac;
    private readonly array $_account;
    private readonly array $_organizations;
    
    public function __construct(string $token, array $rbac, array $account, array $organizations)
    {
        $this->_jwt = $token;
        $this->_rbac = $rbac;
        $this->_account = $account;
        $this->_organizations = $organizations;
        
        \putenv("CODESAUR_ACCOUNT_ID={$this->_account['id']}");
    }

    public function is(string $role): bool
    {
        if (isset($this->_rbac['system_coder'])
            && $this->getAlias() == 'system'
        ) {
            return true;
        }
        
        return isset($this->_rbac[$role]);
    }

    public function can(string $permission, ?string $role = null): bool
    {
        if (isset($this->_rbac['system_coder'])
            && $this->getAlias() == 'system'
        ) {
            return true;
        }
        
        if (!empty($role)) {
            return $this->_rbac[$role][$permission] ?? false;
        }
        
        foreach ($this->_rbac as $role) {
            if (isset($role[$permission])) {
                return $role[$permission] == true;
            }
        }
        
        return false;
    }
    
    public function getToken(): string
    {
        return $this->_jwt;
    }

    public function getAccount(): array
    {
        return $this->_account;
    }

    public function getAlias(): string
    {
        return $this->getOrganization()['alias'] ?? '';
    }
    
    public function getOrganizations(): array
    {
        return $this->_organizations;
    }

    public function getOrganization(): array
    {
        return $this->_organizations[0] ?? [];
    }
        
    public function getRBAC(): array
    {
        return $this->_rbac;
    }
}
