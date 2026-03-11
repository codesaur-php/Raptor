<?php

namespace Tests\Integration\RBAC;

use Tests\Support\IntegrationTestCase;
use Raptor\RBAC\Roles;
use Raptor\RBAC\Permissions;
use Raptor\RBAC\RolePermission;
use Raptor\RBAC\UserRole;

class RolesPermissionsTest extends IntegrationTestCase
{
    public function testRolesTableExists(): void
    {
        $model = new Roles($this->getPdo());
        $tableName = $model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDefaultRolesSeeded(): void
    {
        $model = new Roles($this->getPdo());
        $coder = $model->getRowWhere(['name' => 'coder']);
        $this->assertNotFalse($coder);
        $this->assertEquals('system', $coder['alias']);
    }

    public function testPermissionsTableExists(): void
    {
        $model = new Permissions($this->getPdo());
        $tableName = $model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDefaultPermissionsSeeded(): void
    {
        $model = new Permissions($this->getPdo());
        $perm = $model->getRowWhere(['name' => 'user_index']);
        $this->assertNotFalse($perm);
    }

    public function testRolePermissionTableExists(): void
    {
        $model = new RolePermission($this->getPdo());
        $tableName = $model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testUserRoleTableExists(): void
    {
        $model = new UserRole($this->getPdo());
        $tableName = $model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDefaultUserRoleSeeded(): void
    {
        $model = new UserRole($this->getPdo());
        // user_id=1, role_id=1 (admin -> coder)
        $row = $model->getRowWhere(['user_id' => 1, 'role_id' => 1]);
        $this->assertNotFalse($row);
    }

    public function testInsertRole(): void
    {
        $model = new Roles($this->getPdo());
        $result = $model->insert([
            'name'        => 'test_role_' . uniqid(),
            'description' => 'Test role',
            'alias'       => 'test',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testInsertPermission(): void
    {
        $model = new Permissions($this->getPdo());
        $result = $model->insert([
            'alias'       => 'test',
            'module'      => 'test',
            'name'        => 'test_perm_' . uniqid(),
            'description' => 'Test permission',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }
}
