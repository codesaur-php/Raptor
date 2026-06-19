<?php

namespace Tests\Unit\Migration;

use Tests\Support\RaptorTestCase;

use Raptor\Migration\MigrationRunner;

/**
 * Pure-logic unit tests for `MigrationRunner` methods that do not need a DB
 * (`splitStatements`, `summarize`, `getUserFolderPath`).
 *
 * The runner constructor still asks for a PDO so we hand it a mock - none of
 * these methods touch the DB.
 */
class MigrationRunnerTest extends RaptorTestCase
{
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->createMock(\PDO::class);
        // Default runner нь MySQL - dollar-quoting идэвхгүй
        $pdo->method('getAttribute')->willReturn('mysql');
        $this->runner = new MigrationRunner($pdo, \sys_get_temp_dir());
    }

    // ====================== splitStatements() ======================

    public function testSplitsSimpleStatements(): void
    {
        $sql = "CREATE INDEX i ON t (a); ALTER TABLE t ADD COLUMN b INT;";
        $this->assertSame(
            ['CREATE INDEX i ON t (a)', 'ALTER TABLE t ADD COLUMN b INT'],
            $this->runner->splitStatements($sql)
        );
    }

    public function testIgnoresSemicolonInsideStringLiteral(): void
    {
        $sql = "INSERT INTO t VALUES ('a;b'); INSERT INTO t VALUES ('c');";
        $this->assertSame(
            ["INSERT INTO t VALUES ('a;b')", "INSERT INTO t VALUES ('c')"],
            $this->runner->splitStatements($sql)
        );
    }

    public function testIgnoresLineComments(): void
    {
        $sql = "-- comment with ; in it\nSELECT 1;";
        $this->assertSame(['SELECT 1'], $this->runner->splitStatements($sql));
    }

    public function testHandlesTrailingStatementWithoutSemicolon(): void
    {
        $sql = "SELECT 1; SELECT 2";
        $this->assertSame(['SELECT 1', 'SELECT 2'], $this->runner->splitStatements($sql));
    }

    public function testHandlesPostgresDollarQuoted(): void
    {
        // Dollar-quoting нь зөвхөн PostgreSQL дээр идэвхждэг тул pgsql runner ашиглана
        $pgPdo = $this->createMock(\PDO::class);
        $pgPdo->method('getAttribute')->willReturn('pgsql');
        $runner = new MigrationRunner($pgPdo, \sys_get_temp_dir());

        $sql = "DO \$\$BEGIN; PERFORM 1; END;\$\$ ; SELECT 2;";
        $statements = $runner->splitStatements($sql);
        $this->assertCount(2, $statements);
        $this->assertStringContainsString('BEGIN; PERFORM 1; END;', $statements[0]);
        $this->assertSame('SELECT 2', $statements[1]);
    }

    public function testDollarSignNotDollarQuotedOnMysql(): void
    {
        // MySQL дээр $$ нь dollar-quote биш - statement-ууд ; дээр зөв хуваагдана.
        // (pgsql-д бол $$...$$ хооронд байх ; залгигдах байсан)
        $sql = "SELECT a\$\$b; SELECT 2;";
        $this->assertSame(['SELECT a$$b', 'SELECT 2'], $this->runner->splitStatements($sql));
    }

    public function testBackslashEscapesQuoteOnMysql(): void
    {
        // MySQL: \' нь escaped quote тул string хаагдахгүй -> бүх юм нэг statement
        $sql = "SELECT 'x\\' AS c; SELECT 2;";
        $this->assertCount(1, $this->runner->splitStatements($sql));
    }

    public function testBackslashBeforeQuoteIsLiteralOnPostgres(): void
    {
        // PostgreSQL (standard_conforming_strings): backslash literal тул \' нь
        // quote-г хааж, statement-ууд зөв хуваагдана
        $pgPdo = $this->createMock(\PDO::class);
        $pgPdo->method('getAttribute')->willReturn('pgsql');
        $runner = new MigrationRunner($pgPdo, \sys_get_temp_dir());

        $sql = "SELECT 'x\\' AS c; SELECT 2;";
        $this->assertCount(2, $runner->splitStatements($sql));
    }

    public function testSkipsEmptyStatements(): void
    {
        $sql = ";;; SELECT 1; ;;";
        $this->assertSame(['SELECT 1'], $this->runner->splitStatements($sql));
    }

    // ====================== summarize() ======================

    public function testSummaryUsesFirstLineComment(): void
    {
        $sql = "-- Adds product category column\nALTER TABLE products ADD COLUMN category VARCHAR(100);";
        $this->assertSame('Adds product category column', $this->runner->summarize($sql));
    }

    public function testSummaryFallsBackToStatementsWhenNoComment(): void
    {
        $sql = "ALTER TABLE products ADD COLUMN active TINYINT;\nCREATE INDEX idx_active ON products (active);";
        $summary = $this->runner->summarize($sql);
        $this->assertStringContainsString('ALTER TABLE products', $summary);
        $this->assertStringContainsString('CREATE INDEX', $summary);
    }

    public function testSummaryTruncatesAt500Chars(): void
    {
        $longComment = '-- ' . \str_repeat('x', 700);
        $summary = $this->runner->summarize($longComment);
        $this->assertLessThanOrEqual(500, \strlen($summary));
        $this->assertStringEndsWith('...', $summary);
    }

    public function testSummaryCapsListAtFiveStatements(): void
    {
        $sql = '';
        for ($i = 1; $i <= 8; $i++) {
            $sql .= "SELECT $i;\n";
        }
        $summary = $this->runner->summarize($sql);
        $this->assertStringContainsString('SELECT 1', $summary);
        $this->assertStringContainsString('SELECT 5', $summary);
        $this->assertStringContainsString('...', $summary);
        $this->assertStringNotContainsString('SELECT 6', $summary);
    }

    // ====================== getUserFolderPath() ======================

    public function testFolderPathUsesIdAndUsername(): void
    {
        $path = $this->runner->getUserFolderPath(12, 'john');
        $this->assertSame('12-john', \basename($path));
    }

    public function testFolderPathSanitizesInvalidChars(): void
    {
        $path = $this->runner->getUserFolderPath(42, 'john/../etc');
        // Slashes become _, dots survive (allowed in whitelist), no traversal possible
        $this->assertSame('42-john_.._etc', \basename($path));
    }

    public function testFolderPathStripsTrailingDot(): void
    {
        $path = $this->runner->getUserFolderPath(7, 'john.');
        $this->assertSame('7-john', \basename($path));
    }

    public function testFolderPathStripsLeadingDot(): void
    {
        $path = $this->runner->getUserFolderPath(7, '.john');
        $this->assertSame('7-john', \basename($path));
    }

    public function testFolderPathFallsBackToUserOnEdgeCases(): void
    {
        $this->assertSame('5-user', \basename($this->runner->getUserFolderPath(5, '')));
        $this->assertSame('5-user', \basename($this->runner->getUserFolderPath(5, '.')));
        $this->assertSame('5-user', \basename($this->runner->getUserFolderPath(5, '..')));
        $this->assertSame('5-user', \basename($this->runner->getUserFolderPath(5, 'Наранхүү')));
    }

    public function testFolderPathTruncatesLongUsername(): void
    {
        $long = \str_repeat('a', 200);
        $base = \basename($this->runner->getUserFolderPath(9, $long));
        $this->assertSame(52, \strlen($base)); // "9-" + 50 chars
        $this->assertStringStartsWith('9-', $base);
    }
}
