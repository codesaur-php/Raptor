<?php

namespace Tests\Unit\Migration;

use Tests\Support\RaptorTestCase;
use Raptor\Migration\MigrationRunner;

/**
 * MigrationRunner-ийн цэвэр логикийн тестүүд.
 * DB шаардахгүй method-уудыг шалгана: parseFile(), splitStatements().
 */
class MigrationRunnerTest extends RaptorTestCase
{
    private string $tmpDir;
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/raptor_migration_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        // MigrationRunner-д mock PDO өгнө (parseFile, splitStatements-д хэрэггүй)
        $pdo = $this->createMock(\PDO::class);
        $this->runner = new MigrationRunner($pdo, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        // Temp файлуудыг цэвэрлэх
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ===== parseFile() tests =====

    public function testParseFileWithUpAndDown(): void
    {
        $file = $this->tmpDir . '/test.sql';
        file_put_contents($file, <<<'SQL'
-- [UP]
ALTER TABLE news ADD COLUMN source VARCHAR(255);
CREATE INDEX idx_source ON news (source);

-- [DOWN]
ALTER TABLE news DROP COLUMN source;
SQL);

        $result = $this->runner->parseFile($file);

        $this->assertArrayHasKey('up', $result);
        $this->assertArrayHasKey('down', $result);
        $this->assertStringContainsString('ALTER TABLE news ADD COLUMN source', $result['up']);
        $this->assertStringContainsString('CREATE INDEX idx_source', $result['up']);
        $this->assertStringContainsString('ALTER TABLE news DROP COLUMN source', $result['down']);
    }

    public function testParseFileUpOnly(): void
    {
        $file = $this->tmpDir . '/test.sql';
        file_put_contents($file, <<<'SQL'
-- [UP]
ALTER TABLE users ADD COLUMN phone VARCHAR(20);
SQL);

        $result = $this->runner->parseFile($file);

        $this->assertStringContainsString('ALTER TABLE users ADD COLUMN phone', $result['up']);
        $this->assertEmpty($result['down']);
    }

    public function testParseFileNoMarkers(): void
    {
        $file = $this->tmpDir . '/test.sql';
        file_put_contents($file, 'ALTER TABLE users ADD COLUMN age INT;');

        $result = $this->runner->parseFile($file);

        $this->assertStringContainsString('ALTER TABLE users ADD COLUMN age', $result['up']);
        $this->assertEmpty($result['down']);
    }

    public function testParseFileDownOnly(): void
    {
        $file = $this->tmpDir . '/test.sql';
        file_put_contents($file, <<<'SQL'
-- [DOWN]
DROP INDEX idx_source ON news;
SQL);

        $result = $this->runner->parseFile($file);

        $this->assertEmpty($result['up']);
        $this->assertStringContainsString('DROP INDEX idx_source', $result['down']);
    }

    public function testParseFileCaseInsensitiveMarkers(): void
    {
        $file = $this->tmpDir . '/test.sql';
        file_put_contents($file, <<<'SQL'
-- [up]
ALTER TABLE news ADD COLUMN slug VARCHAR(255);

-- [down]
ALTER TABLE news DROP COLUMN slug;
SQL);

        $result = $this->runner->parseFile($file);

        $this->assertStringContainsString('slug', $result['up']);
        $this->assertStringContainsString('DROP COLUMN slug', $result['down']);
    }

    // ===== splitStatements() tests =====

    public function testSplitSimpleStatements(): void
    {
        $sql = "ALTER TABLE news ADD COLUMN source VARCHAR(255);\nCREATE INDEX idx ON news (source);";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('ALTER TABLE', $result[0]);
        $this->assertStringContainsString('CREATE INDEX', $result[1]);
    }

    public function testSplitIgnoresEmptyStatements(): void
    {
        $sql = "ALTER TABLE news ADD COLUMN x INT;\n;\n;";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
    }

    public function testSplitRespectsStringLiterals(): void
    {
        $sql = "UPDATE news SET title = 'hello; world' WHERE id = 1;";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("'hello; world'", $result[0]);
    }

    public function testSplitRespectsDoubleQuotedStrings(): void
    {
        $sql = 'UPDATE news SET title = "semi;colon" WHERE id = 1;';

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('"semi;colon"', $result[0]);
    }

    public function testSplitSkipsSQLComments(): void
    {
        $sql = "-- This is a comment\nALTER TABLE news ADD COLUMN x INT;";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('ALTER TABLE', $result[0]);
    }

    public function testSplitNoTrailingSemicolon(): void
    {
        $sql = "ALTER TABLE news ADD COLUMN x INT";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
    }

    public function testSplitEmptyInput(): void
    {
        $result = $this->runner->splitStatements('');

        $this->assertCount(0, $result);
    }

    public function testSplitMultipleStatementsWithComments(): void
    {
        $sql = <<<'SQL'
-- Add source column
ALTER TABLE news ADD COLUMN source VARCHAR(255);
-- Add index
CREATE INDEX idx_source ON news (source);
-- Update default
UPDATE news SET source = 'unknown' WHERE source IS NULL;
SQL;

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(3, $result);
    }

    public function testSplitEscapedQuoteInString(): void
    {
        $sql = "UPDATE news SET title = 'it\\'s a test' WHERE id = 1;";

        $result = $this->runner->splitStatements($sql);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("it\\'s a test", $result[0]);
    }

    // ===== hasPending() tests =====

    public function testHasPendingWithNoFiles(): void
    {
        $this->assertFalse($this->runner->hasPending());
    }

    public function testHasPendingWithSqlFiles(): void
    {
        file_put_contents($this->tmpDir . '/add_column.sql', '-- [UP]' . "\n" . 'SELECT 1;');

        $this->assertTrue($this->runner->hasPending());
    }

    public function testHasPendingIgnoresNonSqlFiles(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'not a migration');

        $this->assertFalse($this->runner->hasPending());
    }

    public function testHasPendingIgnoresRanDirectory(): void
    {
        // ran/ дотор SQL файл байгаа ч pending гэж тооцохгүй
        mkdir($this->tmpDir . '/ran', 0755, true);
        file_put_contents($this->tmpDir . '/ran/old.sql', 'SELECT 1;');

        $this->assertFalse($this->runner->hasPending());
    }

    // ===== status() tests =====

    public function testStatusEmptyDirectory(): void
    {
        $status = $this->runner->status();

        $this->assertArrayHasKey('ran', $status);
        $this->assertArrayHasKey('pending', $status);
        $this->assertEmpty($status['ran']);
        $this->assertEmpty($status['pending']);
    }

    public function testStatusWithPendingFiles(): void
    {
        file_put_contents($this->tmpDir . '/add_slug.sql', 'SELECT 1;');
        file_put_contents($this->tmpDir . '/add_source.sql', 'SELECT 1;');

        $status = $this->runner->status();

        $this->assertCount(2, $status['pending']);
        $this->assertContains('add_slug.sql', $status['pending']);
        $this->assertContains('add_source.sql', $status['pending']);
        $this->assertEmpty($status['ran']);
    }

    public function testStatusWithRanFiles(): void
    {
        mkdir($this->tmpDir . '/ran', 0755, true);
        file_put_contents($this->tmpDir . '/ran/old_migration.sql', 'SELECT 1;');

        $status = $this->runner->status();

        $this->assertCount(1, $status['ran']);
        $this->assertEquals('old_migration.sql', $status['ran'][0]['file']);
        $this->assertArrayHasKey('executed_at', $status['ran'][0]);
        $this->assertEmpty($status['pending']);
    }

    public function testStatusMixedPendingAndRan(): void
    {
        file_put_contents($this->tmpDir . '/new_migration.sql', 'SELECT 1;');
        mkdir($this->tmpDir . '/ran', 0755, true);
        file_put_contents($this->tmpDir . '/ran/old_migration.sql', 'SELECT 1;');

        $status = $this->runner->status();

        $this->assertCount(1, $status['pending']);
        $this->assertCount(1, $status['ran']);
    }
}
