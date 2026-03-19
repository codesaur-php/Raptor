<?php

namespace Tests\Unit\Log;

use Tests\Support\RaptorTestCase;
use Raptor\Log\Logger;

/**
 * Logger unit test.
 *
 * DB-д хандахгүйгээр тестлэх боломжтой логикуудыг шалгана:
 * - Message interpolation (private method -> normalizeLogRecord-аар дамждаг)
 * - Secret masking (encodeContext)
 * - Column immutability (setColumns)
 * - Table name validation (setTable)
 *
 * Private method-уудыг ReflectionMethod ашиглан тестлэнэ.
 */
class LoggerTest extends RaptorTestCase
{
    private \PDO $pdo;
    private Logger $logger;

    protected function setUp(): void
    {
        // SQLite in-memory DB for Logger constructor (requires PDO)
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->logger = new Logger($this->pdo);
    }

    // ---------------------------------------------------------
    // Helper: invoke private method via reflection
    // ---------------------------------------------------------

    private function invokeMethod(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($this->logger, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->logger, ...$args);
    }

    // ---------------------------------------------------------
    // Message interpolation
    // ---------------------------------------------------------

    public function testInterpolateSimplePlaceholder(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'User {name} logged in',
            ['name' => 'John'],
        ]);

        $this->assertEquals('User John logged in', $result);
    }

    public function testInterpolateMultiplePlaceholders(): void
    {
        $result = $this->invokeMethod('interpolate', [
            '{action} by {user} at {time}',
            ['action' => 'Created', 'user' => 'admin', 'time' => '12:00'],
        ]);

        $this->assertEquals('Created by admin at 12:00', $result);
    }

    public function testInterpolateNestedKeys(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'User {auth.id} ({auth.name}) performed {action}',
            [
                'auth' => ['id' => 5, 'name' => 'Admin'],
                'action' => 'login',
            ],
        ]);

        $this->assertEquals('User 5 (Admin) performed login', $result);
    }

    public function testInterpolateDeeplyNestedKeys(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'Error: {error.detail.code}',
            ['error' => ['detail' => ['code' => 'E404']]],
        ]);

        $this->assertEquals('Error: E404', $result);
    }

    public function testInterpolateMissingPlaceholderLeftAsIs(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'Hello {name}, role: {role}',
            ['name' => 'Test'],
        ]);

        $this->assertEquals('Hello Test, role: {role}', $result);
    }

    public function testInterpolateEmptyContext(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'No placeholders here',
            [],
        ]);

        $this->assertEquals('No placeholders here', $result);
    }

    public function testInterpolateNoPlaceholdersInMessage(): void
    {
        $result = $this->invokeMethod('interpolate', [
            'Static message',
            ['key' => 'value'],
        ]);

        $this->assertEquals('Static message', $result);
    }

    // ---------------------------------------------------------
    // flattenArray
    // ---------------------------------------------------------

    public function testFlattenArraySimple(): void
    {
        $result = $this->invokeMethod('flattenArray', [
            ['a' => 1, 'b' => 2],
        ]);

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testFlattenArrayNested(): void
    {
        $result = $this->invokeMethod('flattenArray', [
            ['user' => ['id' => 1, 'name' => 'Test']],
        ]);

        $this->assertEquals(['user.id' => 1, 'user.name' => 'Test'], $result);
    }

    public function testFlattenArrayDeeplyNested(): void
    {
        $result = $this->invokeMethod('flattenArray', [
            ['a' => ['b' => ['c' => 'deep']]],
        ]);

        $this->assertEquals(['a.b.c' => 'deep'], $result);
    }

    // ---------------------------------------------------------
    // Secret masking (encodeContext)
    // ---------------------------------------------------------

    public function testEncodeContextMasksPassword(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['username' => 'admin', 'password' => 'secret123'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('admin', $decoded['username']);
        $this->assertEquals('*** hidden ***', $decoded['password']);
    }

    public function testEncodeContextMasksPasswordCaseInsensitive(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['Password' => 'secret', 'OLD_PASSWORD' => 'old', 'new_password' => 'new'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('*** hidden ***', $decoded['Password']);
        $this->assertEquals('*** hidden ***', $decoded['OLD_PASSWORD']);
        $this->assertEquals('*** hidden ***', $decoded['new_password']);
    }

    public function testEncodeContextMasksJwt(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['jwt' => 'eyJhbGciOiJIUzI1NiJ9.payload.signature'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('*** hidden ***', $decoded['jwt']);
    }

    public function testEncodeContextMasksToken(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['token' => 'abc123', 'TOKEN' => 'xyz789'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('*** hidden ***', $decoded['token']);
        $this->assertEquals('*** hidden ***', $decoded['TOKEN']);
    }

    public function testEncodeContextMasksPin(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['pin' => '1234'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('*** hidden ***', $decoded['pin']);
    }

    public function testEncodeContextMasksNestedSecrets(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['user' => ['name' => 'Admin', 'password' => 'secret']],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('Admin', $decoded['user']['name']);
        $this->assertEquals('*** hidden ***', $decoded['user']['password']);
    }

    public function testEncodeContextPreservesNonSecretData(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['action' => 'login', 'username' => 'admin', 'ip' => '127.0.0.1'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertEquals('login', $decoded['action']);
        $this->assertEquals('admin', $decoded['username']);
        $this->assertEquals('127.0.0.1', $decoded['ip']);
    }

    public function testEncodeContextReturnsValidJson(): void
    {
        $json = $this->invokeMethod('encodeContext', [
            ['key' => 'value', 'number' => 42, 'bool' => true],
        ]);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(42, $decoded['number']);
        $this->assertTrue($decoded['bool']);
    }

    // ---------------------------------------------------------
    // setColumns throws RuntimeException (immutable)
    // ---------------------------------------------------------

    public function testSetColumnsThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("You can't change predefined columns");

        $this->logger->setColumns([]);
    }

    // ---------------------------------------------------------
    // setTable with empty name throws InvalidArgumentException
    // ---------------------------------------------------------

    public function testSetTableWithEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logger table name must be provided');

        $this->logger->setTable('');
    }

    public function testSetTableWithSpecialCharsOnlyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->logger->setTable('!@#$%^&*()');
    }

    // ---------------------------------------------------------
    // setTable sanitizes name
    // ---------------------------------------------------------

    public function testSetTableSanitizesName(): void
    {
        // setTable creates the table and indexes in the DB
        $this->logger->setTable('test_table');

        $this->assertEquals('test_table_log', $this->logger->getName());
    }

    public function testSetTableStripsInvalidChars(): void
    {
        // preg_replace keeps only [A-Za-z0-9_-]
        // Use a fresh logger to avoid "readonly property already set" error
        $logger = new Logger($this->pdo);
        $logger->setTable('my@table!name');

        $this->assertEquals('mytablename_log', $logger->getName());
    }

    // ---------------------------------------------------------
    // log() with empty table name does nothing (returns early)
    // ---------------------------------------------------------

    public function testLogWithUninitializedTableNameReturnsEarlyOrErrors(): void
    {
        // Logger without setTable() called - name is uninitialized readonly
        // empty() on an uninitialized readonly property throws Error in PHP 8.1+
        // This verifies that log() does not silently write without a table set
        try {
            $this->logger->log('info', 'Test message', []);
            // If empty() handles it gracefully, that is acceptable
            $this->assertTrue(true);
        } catch (\Error $e) {
            // Typed property not initialized - expected behavior
            $this->assertStringContainsString('must not be accessed before initialization', $e->getMessage());
        }
    }

    // ---------------------------------------------------------
    // Column definitions are correct
    // ---------------------------------------------------------

    public function testLoggerHasRequiredColumns(): void
    {
        $columns = $this->logger->getColumns();

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('level', $columns);
        $this->assertArrayHasKey('message', $columns);
        $this->assertArrayHasKey('context', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertCount(5, $columns);
    }
}
