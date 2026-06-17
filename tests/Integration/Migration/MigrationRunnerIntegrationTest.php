<?php

namespace Tests\Integration\Migration;

use Tests\Support\IntegrationTestCase;
use Raptor\Migration\MigrationRunner;

/**
 * MigrationRunner integration tests.
 *
 * Жинхэнэ MySQL PDO + temp filesystem ашиглан per-user folder уруу
 * apply хийх, амжилтгүй apply, path traversal зэргийг шалгана.
 *
 * DDL nь MySQL дээр auto-commit хийдэг тул tearDown-д тест хүснэгтийг
 * гараар DROP хийнэ.
 */
class MigrationRunnerIntegrationTest extends IntegrationTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = \sys_get_temp_dir() . '/raptor_migration_int_' . \uniqid();
        \mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);

        try {
            static::$pdo->exec('DROP TABLE IF EXISTS _mig_test_items');
        } catch (\Throwable $e) {
        }

        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->removeDir($path) : \unlink($path);
        }
        \rmdir($dir);
    }

    private function createRunner(): MigrationRunner
    {
        return new MigrationRunner(static::$pdo, $this->tmpDir);
    }

    private function userFolder(string $label): string
    {
        $path = $this->tmpDir . '/' . $label;
        \mkdir($path, 0755, true);
        return $path;
    }

    public function testStatusListsFoldersAndPendingFiles(): void
    {
        $folder = $this->userFolder('12-john');
        \file_put_contents($folder . '/test.sql', 'SELECT 1;');

        $status = $this->createRunner()->status();
        $this->assertCount(1, $status['folders']);
        $this->assertSame(12, $status['folders'][0]['user_id']);
        $this->assertSame('john', $status['folders'][0]['username']);
        $this->assertCount(1, $status['folders'][0]['pending']);
        $this->assertCount(0, $status['folders'][0]['ran']);
        $this->assertSame('test.sql', $status['folders'][0]['pending'][0]['file']);
    }

    public function testStatusIgnoresNonUserFolders(): void
    {
        // Random folder that doesn't match {id}-{username} pattern is ignored
        \mkdir($this->tmpDir . '/random-stuff', 0755, true);
        \mkdir($this->tmpDir . '/12-john', 0755, true);

        $status = $this->createRunner()->status();
        $this->assertCount(1, $status['folders']);
        $this->assertSame('12-john', $status['folders'][0]['label']);
    }

    public function testApplyRunsSqlAndMovesFileToRan(): void
    {
        $folder = $this->userFolder('12-john');
        \file_put_contents($folder . '/ddl.sql',
            'CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));'
        );

        $result = $this->createRunner()->apply('12-john', 'ddl.sql');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['statements']);
        $this->assertNotEmpty($result['sha256']);
        $this->assertFileDoesNotExist($folder . '/ddl.sql');
        $this->assertFileExists($folder . '/ran/ddl.sql');

        // Verify the DDL actually ran
        $tables = static::$pdo->query("SHOW TABLES LIKE '_mig_test_items'")->fetchAll();
        $this->assertNotEmpty($tables);
    }

    public function testApplyFailureLeavesFilePending(): void
    {
        $folder = $this->userFolder('12-john');
        \file_put_contents($folder . '/bad.sql', 'SELECT * FROM table_that_does_not_exist_xyz;');

        $result = $this->createRunner()->apply('12-john', 'bad.sql');

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertFileExists($folder . '/bad.sql');
        $this->assertDirectoryDoesNotExist($folder . '/ran');
    }

    public function testApplyMissingFileReturnsError(): void
    {
        $this->userFolder('12-john');
        $result = $this->createRunner()->apply('12-john', 'missing.sql');
        $this->assertFalse($result['ok']);
        $this->assertSame('File not found in pending folder', $result['error']);
    }

    public function testApplyRejectsPathTraversal(): void
    {
        $folder = $this->userFolder('12-john');
        \file_put_contents($folder . '/safe.sql', 'SELECT 1;');

        // .. as folder/file -> resolved to null
        $result = $this->createRunner()->apply('..', 'safe.sql');
        $this->assertFalse($result['ok']);

        $result = $this->createRunner()->apply('12-john', '../safe.sql');
        $this->assertFalse($result['ok']);
    }

    public function testStatusReportsRanFilesSeparately(): void
    {
        $folder = $this->userFolder('12-john');
        \mkdir($folder . '/ran', 0755, true);
        \file_put_contents($folder . '/pending.sql', 'SELECT 1;');
        \file_put_contents($folder . '/ran/applied.sql', 'SELECT 2;');

        $status = $this->createRunner()->status();
        $this->assertCount(1, $status['folders'][0]['pending']);
        $this->assertCount(1, $status['folders'][0]['ran']);
        $this->assertSame('pending.sql', $status['folders'][0]['pending'][0]['file']);
        $this->assertSame('applied.sql', $status['folders'][0]['ran'][0]['file']);
    }

    public function testSummaryExtractsFirstLineComment(): void
    {
        $sql = "-- Adds product category column\nALTER TABLE products ADD COLUMN category VARCHAR(100);";
        $summary = $this->createRunner()->summarize($sql);
        $this->assertSame('Adds product category column', $summary);
    }

    public function testSummaryFallsBackToFirstStatementWhenNoComment(): void
    {
        $sql = "ALTER TABLE products ADD COLUMN active TINYINT(1);\nCREATE INDEX idx_active ON products (active);";
        $summary = $this->createRunner()->summarize($sql);
        $this->assertStringContainsString('ALTER TABLE products', $summary);
        $this->assertStringContainsString('CREATE INDEX', $summary);
    }

    public function testScanDetectsSensitiveSql(): void
    {
        $warnings = $this->createRunner()->scan("UPDATE users SET password = 'x';");
        $this->assertNotEmpty($warnings);
    }

    public function testApplyRenamesIfRanFileExists(): void
    {
        $folder = $this->userFolder('12-john');
        \mkdir($folder . '/ran', 0755, true);
        \file_put_contents($folder . '/ran/dup.sql', '-- previously applied');
        \file_put_contents($folder . '/dup.sql', 'DO 1;');

        $result = $this->createRunner()->apply('12-john', 'dup.sql');

        $this->assertTrue($result['ok']);
        $this->assertFileExists($folder . '/ran/dup.sql'); // original preserved
        // New file got renamed with timestamp suffix
        $ranFiles = \glob($folder . '/ran/dup_*.sql');
        $this->assertCount(1, $ranFiles);
    }

    public function testGetUserFolderPathSanitizesUsername(): void
    {
        $runner = $this->createRunner();
        $path = $runner->getUserFolderPath(42, 'john/../etc');
        // / -> _ ; no leading/trailing to trim
        $this->assertSame('42-john_.._etc', \basename($path));
    }

    public function testGetUserFolderPathStripsTrailingDot(): void
    {
        // Windows NTFS silently strips trailing dots from folder names,
        // so we must do it ourselves to avoid create/lookup mismatches.
        $runner = $this->createRunner();
        $path = $runner->getUserFolderPath(7, 'john.');
        $this->assertSame('7-john', \basename($path));
    }

    public function testGetUserFolderPathStripsLeadingDot(): void
    {
        // Leading dot would make a Unix hidden directory.
        $runner = $this->createRunner();
        $path = $runner->getUserFolderPath(7, '.john');
        $this->assertSame('7-john', \basename($path));
    }

    public function testGetUserFolderPathFallsBackToUserOnDotOnly(): void
    {
        $runner = $this->createRunner();
        $this->assertSame('5-user', \basename($runner->getUserFolderPath(5, '.')));
        $this->assertSame('5-user', \basename($runner->getUserFolderPath(5, '..')));
        $this->assertSame('5-user', \basename($runner->getUserFolderPath(5, '...')));
    }

    public function testGetUserFolderPathFallsBackToUserOnEmpty(): void
    {
        $runner = $this->createRunner();
        $this->assertSame('5-user', \basename($runner->getUserFolderPath(5, '')));
        // Pure Unicode: all chars become _, then trim -> empty -> user
        $this->assertSame('5-user', \basename($runner->getUserFolderPath(5, 'Наранхүү')));
    }

    public function testGetUserFolderPathTruncatesLongUsername(): void
    {
        $runner = $this->createRunner();
        $long = \str_repeat('a', 200);
        $path = $runner->getUserFolderPath(9, $long);
        $base = \basename($path);
        // "9-" + 50 chars = 52 total
        $this->assertSame(52, \strlen($base));
        $this->assertStringStartsWith('9-', $base);
    }

    public function testGetUserFolderPathPreservesAllowedChars(): void
    {
        $runner = $this->createRunner();
        $path = $runner->getUserFolderPath(12, 'john_doe-99');
        $this->assertSame('12-john_doe-99', \basename($path));
    }
}
