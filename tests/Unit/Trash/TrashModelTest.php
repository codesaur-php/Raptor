<?php

namespace Tests\Unit\Trash;

use PHPUnit\Framework\TestCase;

use Raptor\Trash\TrashModel;

/**
 * TrashModel-ийн source code шинжлэлийн тест.
 * DB холболт шаардахгүй.
 */
class TrashModelTest extends TestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/trash/TrashModel.php'
        );
    }

    public function testModelExtendsDataObjectModel(): void
    {
        $this->assertStringContainsString(
            'extends Model',
            self::$source,
            'TrashModel must extend codesaur\DataObject\Model'
        );
    }

    public function testTableNameIsTrash(): void
    {
        $this->assertStringContainsString(
            "setTable('trash')",
            self::$source,
            'Table name must be trash'
        );
    }

    public function testHasRequiredColumns(): void
    {
        $columns = ['id', 'table_name', 'log_table', 'original_id', 'record_data', 'deleted_by', 'deleted_at'];
        foreach ($columns as $col) {
            $this->assertStringContainsString(
                "'$col'",
                self::$source,
                "TrashModel must have $col column"
            );
        }
    }

    public function testRecordDataIsMediumtext(): void
    {
        $this->assertStringContainsString(
            "'mediumtext'",
            self::$source,
            'record_data must be mediumtext for large JSON records'
        );
    }

    public function testStoreMethodExists(): void
    {
        $this->assertStringContainsString(
            'function store(',
            self::$source,
            'TrashModel must have store() method'
        );
    }

    public function testStoreUsesJsonEncode(): void
    {
        $this->assertStringContainsString(
            'json_encode($recordData',
            self::$source,
            'store() must JSON encode record data'
        );
    }

    public function testStoreUsesUnescapedUnicode(): void
    {
        $this->assertStringContainsString(
            'JSON_UNESCAPED_UNICODE',
            self::$source,
            'store() must use JSON_UNESCAPED_UNICODE for Mongolian text'
        );
    }

    public function testHasIndexOnTableName(): void
    {
        $this->assertStringContainsString(
            'idx_table',
            self::$source,
            'TrashModel must have index on table_name column'
        );
    }

    public function testHasIndexOnDeletedAt(): void
    {
        $this->assertStringContainsString(
            'idx_deleted',
            self::$source,
            'TrashModel must have index on deleted_at column'
        );
    }
}
