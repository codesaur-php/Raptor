<?php

namespace Tests\Integration\Model;

use Tests\Support\IntegrationTestCase;
use Raptor\Organization\OrganizationModel;

class OrganizationModelTest extends IntegrationTestCase
{
    private OrganizationModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrganizationModel($this->getPdo());
    }

    public function testTableExists(): void
    {
        $tableName = $this->model->getName();
        $stmt = $this->getPdo()->query("SHOW TABLES LIKE '$tableName'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testInsertOrganization(): void
    {
        $result = $this->model->insert([
            'name'  => 'Test Org ' . uniqid(),
            'alias' => 'test',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testGetById(): void
    {
        $inserted = $this->model->insert([
            'name'  => 'Find Org ' . uniqid(),
            'alias' => 'find',
        ]);

        $found = $this->model->getRowWhere(['id' => (int) $inserted['id']]);
        $this->assertIsArray($found);
        $this->assertEquals($inserted['id'], $found['id']);
    }
}
