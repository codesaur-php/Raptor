<?php

namespace Raptor\Authentication;

use Exception;

class User implements RBACUserInterface
{
    private $_rbac;
    private $_account;
    private $_organizations;
    
    function __construct(array $data)
    {
        if (empty($data['rbac'])
                || empty($data['account']['id'])
                || empty($data['organizations'][0]['id'])
        ) {           
            throw new Exception('Invalid RBAC user information!');
        }

        $this->_rbac = $data['rbac'];
        $this->_account = $data['account'];
        $this->_organizations = $data['organizations'];
        
        putenv("CODESAUR_ACCOUNT_ID={$this->_account['id']}");
    }

    public function is($role): bool
    {        
        if (isset($this->_rbac['system_coder'])) {
            return true;
        }
        
        return isset($this->_rbac[$role]);
    }

    public function can($permission, $role = null): bool
    {
        if (isset($this->_rbac['system_coder'])) {
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

    public function getAccount(): array
    {
        return $this->_account;
    }
    
    public function getOrganization(): array
    {
        return $this->_organizations[0];
    }
    
    public function getOrganizations(): array
    {
        return $this->_organizations;
    }
}
