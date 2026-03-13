<?php

namespace Tests\Integration\Migration;

use Tests\Support\IntegrationTestCase;
use Raptor\Migration\MigrationRunner;

/**
 * MigrationRunner integration тестүүд.
 * Жинхэнэ MySQL PDO ашиглан migrate(), lock, partial failure зэргийг шалгана.
 */
class MigrationRunnerIntegrationTest extends IntegrationTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/raptor_migration_int_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);

        // Тест-д үүсгэсэн хүснэгтүүдийг цэвэрлэх
        try {
            static::$pdo->exec('DROP TABLE IF EXISTS _mig_test_items');
            static::$pdo->exec('DROP TABLE IF EXISTS _mig_test_logs');
        } catch (\Throwable $e) {
        }

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

    private function createRunner(): MigrationRunner
    {
        return new MigrationRunner(static::$pdo, $this->tmpDir);
    }

    // ===== migrate() tests =====

    public function testMigrateCreatesRanDirectory(): void
    {
        file_put_contents($this->tmpDir . '/create_test.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertCount(1, $migrated);
        $this->assertContains('create_test.sql', $migrated);
        $this->assertDirectoryExists($this->tmpDir . '/ran');
    }

    public function testMigrateMovesFileToRan(): void
    {
        file_put_contents($this->tmpDir . '/create_test.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        $runner = $this->createRunner();
        $runner->migrate();

        $this->assertFileDoesNotExist($this->tmpDir . '/create_test.sql');
        $this->assertFileExists($this->tmpDir . '/ran/create_test.sql');
    }

    public function testMigrateExecutesSQL(): void
    {
        file_put_contents($this->tmpDir . '/create_test.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));

-- [DOWN]
DROP TABLE IF EXISTS _mig_test_items;
SQL);

        $runner = $this->createRunner();
        $runner->migrate();

        // Хүснэгт үүссэн эсэхийг шалгах
        $stmt = static::$pdo->query("SHOW TABLES LIKE '_mig_test_items'");
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        $this->assertNotFalse($result);
    }

    public function testMigrateMultipleFiles(): void
    {
        file_put_contents($this->tmpDir . '/001_items.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        file_put_contents($this->tmpDir . '/002_logs.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_logs (id INT PRIMARY KEY AUTO_INCREMENT, message TEXT);
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertCount(2, $migrated);
        $this->assertEquals('001_items.sql', $migrated[0]);
        $this->assertEquals('002_logs.sql', $migrated[1]);
        $this->assertFalse($runner->hasPending());
    }

    public function testMigrateNoPendingReturnsEmpty(): void
    {
        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertEmpty($migrated);
    }

    public function testMigrateWithMultipleStatements(): void
    {
        file_put_contents($this->tmpDir . '/multi.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
INSERT INTO _mig_test_items (name) VALUES ('alpha');
INSERT INTO _mig_test_items (name) VALUES ('beta');
SQL);

        $runner = $this->createRunner();
        $runner->migrate();

        $stmt = static::$pdo->query('SELECT COUNT(*) FROM _mig_test_items');
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        $this->assertEquals(2, $count);
    }

    // ===== Partial failure + DOWN rollback =====

    public function testMigratePartialFailureRunsDown(): void
    {
        // UP: table үүсгээд, дараа нь буруу SQL → fail
        // DOWN: table устгах
        file_put_contents($this->tmpDir . '/fail_test.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
THIS_IS_INVALID_SQL;

-- [DOWN]
DROP TABLE IF EXISTS _mig_test_items;
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        // Fail болсон тул migrated-д ороогүй
        $this->assertEmpty($migrated);

        // DOWN ажилласан тул table устсан байх ёстой
        $stmt = static::$pdo->query("SHOW TABLES LIKE '_mig_test_items'");
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        $this->assertFalse($result);
    }

    public function testMigratePartialFailureFileStaysPending(): void
    {
        file_put_contents($this->tmpDir . '/fail_test.sql', <<<'SQL'
-- [UP]
INVALID SQL STATEMENT HERE;
SQL);

        $runner = $this->createRunner();
        $runner->migrate();

        // Файл pending хэвээр
        $this->assertFileExists($this->tmpDir . '/fail_test.sql');
        $this->assertTrue($runner->hasPending());
    }

    public function testMigratePartialFailureOtherFilesStillRun(): void
    {
        // Эхний файл амжилттай, хоёр дахь fail, гурав дахь ч ажиллана
        file_put_contents($this->tmpDir . '/001_ok.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        file_put_contents($this->tmpDir . '/002_fail.sql', <<<'SQL'
-- [UP]
TOTALLY_INVALID_STATEMENT;
SQL);

        file_put_contents($this->tmpDir . '/003_ok.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_logs (id INT PRIMARY KEY AUTO_INCREMENT, message TEXT);
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        // 001 ба 003 амжилттай, 002 fail
        $this->assertContains('001_ok.sql', $migrated);
        $this->assertContains('003_ok.sql', $migrated);
        $this->assertNotContains('002_fail.sql', $migrated);

        // 002 pending хэвээр
        $this->assertFileExists($this->tmpDir . '/002_fail.sql');
    }

    // ===== status() with real migrations =====

    public function testStatusAfterMigration(): void
    {
        file_put_contents($this->tmpDir . '/done.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);
        file_put_contents($this->tmpDir . '/todo.sql', <<<'SQL'
-- [UP]
INVALID_WILL_FAIL;
SQL);

        $runner = $this->createRunner();
        $runner->migrate();

        $status = $runner->status();

        // done.sql -> ran, todo.sql -> pending
        $this->assertCount(1, $status['ran']);
        $this->assertEquals('done.sql', $status['ran'][0]['file']);
        $this->assertCount(1, $status['pending']);
        $this->assertContains('todo.sql', $status['pending']);
    }

    // ===== Lock mechanism =====

    public function testMigrateAcquiresAndReleasesLock(): void
    {
        file_put_contents($this->tmpDir . '/lock_test.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertCount(1, $migrated);

        // Lock дахин авч болох ёстой (release хийгдсэн)
        $stmt = static::$pdo->query("SELECT GET_LOCK('raptor_migration', 0)");
        $locked = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();
        $this->assertTrue($locked);

        // Цэвэрлэх
        $stmt = static::$pdo->query("SELECT RELEASE_LOCK('raptor_migration')");
        $stmt->closeCursor();
    }

    // ===== File naming freedom =====

    public function testMigrateAcceptsAnyFileName(): void
    {
        file_put_contents($this->tmpDir . '/add_source_to_news.sql', <<<'SQL'
-- [UP]
CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));
SQL);

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertContains('add_source_to_news.sql', $migrated);
    }

    public function testMigrateFileWithoutMarkers(): void
    {
        // Marker-гүй файл бүхэлдээ UP гэж тооцогдоно
        file_put_contents($this->tmpDir . '/plain.sql', 'CREATE TABLE IF NOT EXISTS _mig_test_items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50));');

        $runner = $this->createRunner();
        $migrated = $runner->migrate();

        $this->assertContains('plain.sql', $migrated);

        $stmt = static::$pdo->query("SHOW TABLES LIKE '_mig_test_items'");
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();
        $this->assertNotFalse($result);
    }
}
