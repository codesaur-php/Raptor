<?php

namespace Tests\Unit\Migration;

use Tests\Support\RaptorTestCase;
use Raptor\Migration\MigrationSecurityScanner;

/**
 * Unit tests for the static SQL security scanner.
 *
 * Зорилго: sensitive-table эсвэл privilege-altering pattern илрэхэд
 * warning буцаах, ердийн (DDL on non-sensitive tables) дээр false-positive
 * үүсгэхгүй байх. Мөн SQL comment / string literal дотрох "fake match"-аас
 * сэргийлдэг эсэхийг шалгах.
 */
class MigrationSecurityScannerTest extends RaptorTestCase
{
    private MigrationSecurityScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new MigrationSecurityScanner();
    }

    public function testSafeAlterTableReturnsNoWarnings(): void
    {
        $sql = 'ALTER TABLE products ADD COLUMN category VARCHAR(100);';
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testSafeCreateIndexReturnsNoWarnings(): void
    {
        $sql = 'CREATE INDEX idx_products_category ON products (category);';
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testSafeInsertIntoTranslationsReturnsNoWarnings(): void
    {
        $sql = "INSERT INTO localization_text (keyword, type) VALUES ('foo', 'sys-defined');";
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testCreateTableIsFlagged(): void
    {
        // Model classes own table creation; migrations should not duplicate that responsibility.
        $sql = 'CREATE TABLE products (id INT PRIMARY KEY);';
        $warnings = $this->scanner->scan($sql);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('CREATE TABLE', $warnings[0]['reason']);
    }

    public function testCreateTableIfNotExistsIsFlagged(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS foo (id INT);';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testCreateTemporaryTableIsFlagged(): void
    {
        $sql = 'CREATE TEMPORARY TABLE foo (id INT);';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testCreateUserDoesNotDoubleMatchAsCreateTable(): void
    {
        // CREATE USER should match its own pattern, not the CREATE TABLE one.
        $sql = "CREATE USER 'x'@'%' IDENTIFIED BY 'p';";
        $warnings = $this->scanner->scan($sql);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('database user', $warnings[0]['reason']);
    }

    public function testUpdateUsersPasswordIsFlagged(): void
    {
        $sql = "UPDATE users SET password = 'pwned' WHERE id = 1;";
        $warnings = $this->scanner->scan($sql);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('users', $warnings[0]['reason']);
    }

    public function testInsertIntoUsersIsFlagged(): void
    {
        $sql = "INSERT INTO users (username, password) VALUES ('h', 'x');";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testDeleteFromUsersIsFlagged(): void
    {
        $sql = "DELETE FROM users WHERE id = 1;";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testDropUsersTableIsFlagged(): void
    {
        $sql = 'DROP TABLE users;';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testTruncateUsersIsFlagged(): void
    {
        $sql = 'TRUNCATE TABLE users;';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testRbacUserRoleInsertIsFlagged(): void
    {
        // Privilege escalation vector: assign coder role to attacker
        $sql = 'INSERT INTO rbac_user_role (user_id, role_id) VALUES (5, 1);';
        $warnings = $this->scanner->scan($sql);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('RBAC', $warnings[0]['reason']);
    }

    public function testRbacRolePermissionUpdateIsFlagged(): void
    {
        $sql = "UPDATE rbac_role_permission SET role_id = 1 WHERE permission_id = 99;";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testDmlAndUnrelatedSensitiveRefInSeparateStatementsNotFlagged(): void
    {
        // UPDATE (statement 1) ба rbac_ (statement 2, DML биш SELECT) өөр statement-д
        // байгаа тул RBAC warning гарах ёсгүй. `.*` cross-statement false-match regression.
        $sql = "UPDATE products SET price = 10; SELECT * FROM rbac_roles;";
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testDropRbacTableIsFlagged(): void
    {
        $sql = 'DROP TABLE rbac_roles;';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testOrganizationsInsertIsFlagged(): void
    {
        $sql = "INSERT INTO organizations (name, alias) VALUES ('hack', 'h');";
        $warnings = $this->scanner->scan($sql);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('organizations', $warnings[0]['reason']);
    }

    public function testOrganizationsUsersUpdateIsFlagged(): void
    {
        $sql = 'UPDATE organizations_users SET organization_id = 99 WHERE user_id = 1;';
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testLocalizationLanguageUpdateIsFlagged(): void
    {
        $sql = 'UPDATE localization_language SET is_active = 0;';
        $warnings = $this->scanner->scan($sql);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('localization_language', $warnings[0]['reason']);
    }

    public function testRaptorMenuDeleteIsFlagged(): void
    {
        $sql = 'DELETE FROM raptor_menu WHERE id > 0;';
        $warnings = $this->scanner->scan($sql);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('raptor_menu', $warnings[0]['reason']);
    }

    public function testGrantIsFlagged(): void
    {
        $sql = "GRANT ALL ON *.* TO 'evil'@'%';";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testRevokeIsFlagged(): void
    {
        $sql = "REVOKE ALL ON db.* FROM 'admin'@'localhost';";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testCreateUserIsFlagged(): void
    {
        $sql = "CREATE USER 'evil'@'%' IDENTIFIED BY 'x';";
        $this->assertNotEmpty($this->scanner->scan($sql));
    }

    public function testCaseInsensitiveMatch(): void
    {
        $upper = "UPDATE USERS SET password = 'x';";
        $lower = "update users set password = 'x';";
        $mixed = "uPdAtE Users SET PASSWORD = 'x';";
        $this->assertNotEmpty($this->scanner->scan($upper));
        $this->assertNotEmpty($this->scanner->scan($lower));
        $this->assertNotEmpty($this->scanner->scan($mixed));
    }

    public function testSqlCommentDoesNotTriggerFalsePositive(): void
    {
        $sql = "-- TODO: UPDATE users SET password = 'x' someday\nALTER TABLE products ADD COLUMN active TINYINT;";
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testBlockCommentDoesNotTriggerFalsePositive(): void
    {
        $sql = "/* sample: UPDATE users SET password = 'x' */\nALTER TABLE products ADD COLUMN x INT;";
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testStringLiteralDoesNotTriggerFalsePositive(): void
    {
        // Audit-style INSERT that mentions another command inside a string.
        $sql = "INSERT INTO audit_log (msg) VALUES ('UPDATE users SET password = ...');";
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testRbacPartialNameDoesNotFalseMatch(): void
    {
        // "rbac_" prefix matches only real RBAC tables, not a column named rbac_status.
        // The pattern requires a write to a table named like rbac_*; column references
        // in a SELECT or ALTER should not flag.
        $sql = 'ALTER TABLE foo ADD COLUMN rbac_status INT;';
        $this->assertEmpty($this->scanner->scan($sql));
    }

    public function testMultipleSensitivePatternsProduceMultipleWarnings(): void
    {
        $sql = "UPDATE users SET email = 'x';\nINSERT INTO rbac_user_role (user_id, role_id) VALUES (1,1);";
        $warnings = $this->scanner->scan($sql);
        $this->assertGreaterThanOrEqual(2, \count($warnings));
    }
}
