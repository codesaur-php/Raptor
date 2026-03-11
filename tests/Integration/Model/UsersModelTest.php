<?php

namespace Tests\Integration\Model;

use Tests\Support\IntegrationTestCase;
use Raptor\User\UsersModel;

class UsersModelTest extends IntegrationTestCase
{
    private UsersModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UsersModel($this->getPdo());
    }

    public function testTableExists(): void
    {
        $tableName = $this->model->getName();
        $this->assertNotEmpty($tableName);

        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testInitialAdminSeeded(): void
    {
        $admin = $this->model->getRowWhere(['username' => 'admin']);
        $this->assertNotFalse($admin);
        $this->assertEquals('admin@example.com', $admin['email']);
        $this->assertEquals('Admin', $admin['first_name']);
    }

    public function testInsertUser(): void
    {
        $result = $this->model->insert([
            'username'   => 'testuser_' . uniqid(),
            'email'      => 'test_' . uniqid() . '@example.com',
            'password'   => password_hash('secret', PASSWORD_BCRYPT),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => '+97699999999',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Test', $result['first_name']);
    }

    public function testInsertAutoCreatedAt(): void
    {
        $result = $this->model->insert([
            'username'   => 'timetest_' . uniqid(),
            'email'      => 'time_' . uniqid() . '@example.com',
            'first_name' => 'Time',
            'last_name'  => 'Test',
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['created_at']);
    }

    public function testGetById(): void
    {
        $inserted = $this->model->insert([
            'username'   => 'getbyid_' . uniqid(),
            'email'      => 'getbyid_' . uniqid() . '@example.com',
            'first_name' => 'Get',
            'last_name'  => 'ById',
        ]);

        $found = $this->model->getRowWhere(['id' => (int) $inserted['id']]);
        $this->assertIsArray($found);
        $this->assertEquals($inserted['id'], $found['id']);
        $this->assertEquals('Get', $found['first_name']);
    }

    public function testUpdateById(): void
    {
        $inserted = $this->model->insert([
            'username'   => 'upd_' . uniqid(),
            'email'      => 'upd_' . uniqid() . '@example.com',
            'first_name' => 'Before',
            'last_name'  => 'Update',
        ]);

        $updated = $this->model->updateById((int) $inserted['id'], [
            'first_name' => 'After',
        ]);

        $this->assertIsArray($updated);
        $this->assertEquals('After', $updated['first_name']);
    }

    public function testDeleteById(): void
    {
        $inserted = $this->model->insert([
            'username'   => 'del_' . uniqid(),
            'email'      => 'del_' . uniqid() . '@example.com',
            'first_name' => 'Delete',
            'last_name'  => 'Me',
        ]);

        $deleted = $this->model->deleteById((int) $inserted['id']);
        $this->assertTrue($deleted);

        $found = $this->model->getRowWhere(['id' => (int) $inserted['id']]);
        $this->assertNull($found);
    }

    public function testUniqueUsernameConstraint(): void
    {
        $username = 'unique_' . uniqid();

        $this->model->insert([
            'username'   => $username,
            'email'      => 'u1_' . uniqid() . '@example.com',
            'first_name' => 'First',
            'last_name'  => 'User',
        ]);

        $this->expectException(\Throwable::class);

        $this->model->insert([
            'username'   => $username,
            'email'      => 'u2_' . uniqid() . '@example.com',
            'first_name' => 'Duplicate',
            'last_name'  => 'User',
        ]);
    }

    public function testUniqueEmailConstraint(): void
    {
        $email = 'unique_' . uniqid() . '@example.com';

        $this->model->insert([
            'username'   => 'e1_' . uniqid(),
            'email'      => $email,
            'first_name' => 'First',
            'last_name'  => 'User',
        ]);

        $this->expectException(\Throwable::class);

        $this->model->insert([
            'username'   => 'e2_' . uniqid(),
            'email'      => $email,
            'first_name' => 'Duplicate',
            'last_name'  => 'User',
        ]);
    }
}
