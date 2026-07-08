<?php

namespace Tests\Integration\Model;

use Tests\Support\IntegrationTestCase;

use Dashboard\Authentication\SignupModel;

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

    public function testInsertDefaultsToPendingStatus(): void
    {
        $result = $this->model->insert([
            'username' => 'sup_' . uniqid(),
            'email'    => 'sup_' . uniqid() . '@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);

        $this->assertSame(SignupModel::STATUS_PENDING, $result['status']);
        $this->assertEmpty($result['verified_at']);
    }

    public function testUpdateById(): void
    {
        $inserted = $this->model->insert([
            'username' => 'sup_' . uniqid(),
            'email'    => 'sup_' . uniqid() . '@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);

        $updated = $this->model->updateById((int) $inserted['id'], [
            'status' => SignupModel::STATUS_REJECTED,
        ]);

        $this->assertIsArray($updated);
        $this->assertSame(SignupModel::STATUS_REJECTED, $updated['status']);
        $this->assertNotEmpty($updated['updated_at']);
    }

    public function testUniqueUsernameAndEmail(): void
    {
        $username = 'sup_' . uniqid();
        $email    = 'sup_' . uniqid() . '@example.com';
        $this->model->insert([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);

        // Ижил username-тэй хоёр дахь хүсэлт UNIQUE constraint-д тулна
        $this->expectException(\PDOException::class);
        $this->model->insert([
            'username' => $username,
            'email'    => 'other_' . uniqid() . '@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);
    }
}
