<?php

namespace Tests\Integration\Model;

use Tests\Support\IntegrationTestCase;
use Raptor\Authentication\SignupModel;

class SignupModelTest extends IntegrationTestCase
{
    private SignupModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SignupModel($this->getPdo());
    }

    public function testTableExists(): void
    {
        $tableName = $this->model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testInsertSignup(): void
    {
        $result = $this->model->insert([
            'username' => 'signup_' . uniqid(),
            'email'    => 'signup_' . uniqid() . '@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
            'code'     => 'mn',
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['created_at']);
    }

    public function testUpdateById(): void
    {
        $inserted = $this->model->insert([
            'username' => 'sup_' . uniqid(),
            'email'    => 'sup_' . uniqid() . '@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);

        $updated = $this->model->updateById((int) $inserted['id'], [
            'is_active' => 0,
        ]);

        $this->assertIsArray($updated);
        $this->assertNotEmpty($updated['updated_at']);
    }
}
