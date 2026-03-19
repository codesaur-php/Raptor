<?php

namespace Tests\Unit\Log;

use PHPUnit\Framework\TestCase;

/**
 * LogsController::retrieve() - SQL injection хамгаалалтын тест.
 *
 * data-context (CONTEXT), ORDER BY, LIMIT зэрэг client-ээс ирсэн
 * утгуудыг sanitize хийж байгаа эсэхийг шалгана.
 *
 * Энэ тест нь retrieve() дотор хийгдэж буй sanitization логикийг
 * шууд давтан шалгаж байна (controller-г бүтнээр дуудахгүй).
 */
class LogsRetrieveSanitizationTest extends TestCase
{
    // =========================================================================
    // CONTEXT field name sanitization
    // =========================================================================

    /**
     * Зөвшөөрөгдөх field нэрүүд.
     *
     * @dataProvider validFieldNamesProvider
     */
    public function testValidFieldNamesAreAccepted(string $field): void
    {
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_.]+$/', $field);
    }

    public static function validFieldNamesProvider(): array
    {
        return [
            'simple'      => ['action'],
            'with_number' => ['record_id'],
            'dotted'      => ['auth_user.id'],
            'deep_nested' => ['context.auth_user.username'],
            'all_digits'  => ['123'],
            'mixed'       => ['a1_b2.c3'],
        ];
    }

    /**
     * SQL injection оролдлого бүхий field нэрүүд хаагдах ёстой.
     *
     * @dataProvider maliciousFieldNamesProvider
     */
    public function testMaliciousFieldNamesAreRejected(string $field): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/^[a-zA-Z0-9_.]+$/',
            $field,
            "Dangerous field name [$field] must be rejected by sanitization regex"
        );
    }

    public static function maliciousFieldNamesProvider(): array
    {
        return [
            'sql_injection_quote'       => ["') OR 1=1 --"],
            'sql_injection_semicolon'   => ["action; DROP TABLE users --"],
            'sql_injection_union'       => ["action UNION SELECT * FROM users --"],
            'sql_injection_subquery'    => ["action') AND (SELECT password FROM users LIMIT 1)='"],
            'parentheses'              => ['action()'],
            'spaces'                   => ['action name'],
            'single_quote'             => ["action'"],
            'double_quote'             => ['action"'],
            'backslash'                => ['action\\'],
            'dash'                     => ['action-name'],
            'equals'                   => ['action=1'],
            'asterisk_in_key'          => ['action*'],
            'comma'                    => ['a,b'],
            'angle_brackets'           => ['<script>'],
            'comment_marker'           => ['action--'],
            'hash_comment'             => ['action#'],
            'null_byte'                => ["action\x00"],
        ];
    }

    // =========================================================================
    // CONTEXT value type check
    // =========================================================================

    /**
     * String бус value-ууд алгасагдах ёстой.
     */
    public function testNonStringValuesAreSkipped(): void
    {
        $context = [
            'action'    => 'login',           // valid
            'bad_array' => ['nested'],         // invalid - array
            'bad_int'   => 123,                // invalid - int
            'bad_bool'  => true,               // invalid - bool
            'bad_null'  => null,               // invalid - null
        ];

        $accepted = [];
        foreach ($context as $field => $value) {
            if (\is_string($value) && \is_string($field)) {
                $accepted[$field] = $value;
            }
        }

        $this->assertCount(1, $accepted);
        $this->assertArrayHasKey('action', $accepted);
    }

    /**
     * Non-string field key мөн алгасагдах ёстой.
     */
    public function testNonStringFieldKeysAreSkipped(): void
    {
        // PHP дээр numeric key автоматаар int болдог
        $context = [0 => 'value', 1 => 'other'];

        $accepted = [];
        foreach ($context as $field => $value) {
            if (\is_string($value) && \is_string($field)) {
                $accepted[$field] = $value;
            }
        }

        $this->assertCount(0, $accepted);
    }

    // =========================================================================
    // ORDER BY sanitization
    // =========================================================================

    /**
     * Зөв ORDER BY утга зөвшөөрөгдөнө.
     *
     * @dataProvider validOrderByProvider
     */
    public function testValidOrderByIsAccepted(string $orderBy): void
    {
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z_]+\s+(ASC|DESC|asc|desc)$/i',
            $orderBy
        );
    }

    public static function validOrderByProvider(): array
    {
        return [
            'id_desc'         => ['id Desc'],
            'id_asc'          => ['id ASC'],
            'created_at_desc' => ['created_at DESC'],
            'level_asc'       => ['level asc'],
        ];
    }

    /**
     * SQL injection оролдлого бүхий ORDER BY утгууд хаагдах ёстой.
     *
     * @dataProvider maliciousOrderByProvider
     */
    public function testMaliciousOrderByIsRejected(string $orderBy): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/^[a-zA-Z_]+\s+(ASC|DESC|asc|desc)$/i',
            $orderBy,
            "Dangerous ORDER BY [$orderBy] must be rejected"
        );
    }

    public static function maliciousOrderByProvider(): array
    {
        return [
            'injection_semicolon'      => ['id DESC; DROP TABLE users'],
            'injection_union'          => ['id DESC UNION SELECT * FROM users'],
            'injection_subquery'       => ['(SELECT password FROM users) DESC'],
            'injection_comment'        => ['id DESC -- comment'],
            'multiple_columns'         => ['id DESC, created_at ASC'],
            'function_call'            => ['SLEEP(5) DESC'],
            'number_in_column'         => ['1 DESC'],
            'dot_notation'             => ['t.id DESC'],
            'backtick_column'          => ['`id` DESC'],
            'expression'               => ['id+1 DESC'],
            'if_function'              => ['IF(1=1,id,level) DESC'],
            'case_expression'          => ['CASE WHEN 1=1 THEN id END DESC'],
            'benchmark'                => ['BENCHMARK(1000000,SHA1(1)) DESC'],
            'no_direction'             => ['id'],
            'only_direction'           => ['DESC'],
            'empty_string'             => [''],
        ];
    }

    // =========================================================================
    // LIMIT sanitization
    // =========================================================================

    /**
     * Зөв LIMIT (integer) зөвшөөрөгдөнө.
     */
    public function testValidLimitIsAccepted(): void
    {
        $this->assertNotFalse(\filter_var(100, \FILTER_VALIDATE_INT));
        $this->assertNotFalse(\filter_var(10000, \FILTER_VALIDATE_INT));
        $this->assertNotFalse(\filter_var(1, \FILTER_VALIDATE_INT));
    }

    /**
     * SQL injection оролдлого бүхий LIMIT утгууд хаагдах ёстой.
     *
     * @dataProvider maliciousLimitProvider
     */
    public function testMaliciousLimitIsRejected(mixed $limit): void
    {
        $this->assertFalse(
            \filter_var($limit, \FILTER_VALIDATE_INT),
            "Dangerous LIMIT [$limit] must be rejected"
        );
    }

    public static function maliciousLimitProvider(): array
    {
        return [
            'string_injection'  => ['100; DROP TABLE users'],
            'union'             => ['100 UNION SELECT * FROM users'],
            'float'             => ['1.5'],
            'hex'               => ['0x1F'],
            'expression'        => ['1+1'],
            'subquery'          => ['(SELECT COUNT(*) FROM users)'],
            'empty'             => [''],
            'null_string'       => ['null'],
            'boolean_string'    => ['true'],
        ];
    }

    // =========================================================================
    // WHERE/бусад key-ууд бүрэн хаагдсан
    // =========================================================================

    /**
     * Client-ээс ирсэн WHERE, HAVING, JOIN зэрэг аюултай key-ууд
     * safeCondition-д орохгүй.
     */
    public function testDangerousConditionKeysAreStripped(): void
    {
        $clientCondition = [
            'ORDER BY' => 'id DESC',
            'LIMIT'    => 10000,
            'WHERE'    => "1=1 UNION SELECT * FROM users",
            'HAVING'   => "1=1",
            'JOIN'     => "users ON 1=1",
            'GROUP BY' => "id",
            'INTO'     => "OUTFILE '/tmp/hack'",
        ];

        // retrieve() дотор яг ийм логик ажилладаг
        $safeCondition = [];
        if (!empty($clientCondition['ORDER BY'])
            && \preg_match('/^[a-zA-Z_]+\s+(ASC|DESC|asc|desc)$/i', $clientCondition['ORDER BY'])
        ) {
            $safeCondition['ORDER BY'] = $clientCondition['ORDER BY'];
        }
        if (!empty($clientCondition['LIMIT'])
            && \filter_var($clientCondition['LIMIT'], \FILTER_VALIDATE_INT)
        ) {
            $safeCondition['LIMIT'] = (int) $clientCondition['LIMIT'];
        }

        // Зөвхөн ORDER BY, LIMIT үлдсэн
        $this->assertArrayHasKey('ORDER BY', $safeCondition);
        $this->assertArrayHasKey('LIMIT', $safeCondition);
        $this->assertCount(2, $safeCondition, 'Only ORDER BY and LIMIT should survive sanitization');

        // Аюултай key-ууд хаагдсан
        $this->assertArrayNotHasKey('WHERE', $safeCondition);
        $this->assertArrayNotHasKey('HAVING', $safeCondition);
        $this->assertArrayNotHasKey('JOIN', $safeCondition);
        $this->assertArrayNotHasKey('GROUP BY', $safeCondition);
        $this->assertArrayNotHasKey('INTO', $safeCondition);
    }

    // =========================================================================
    // Table name sanitization
    // =========================================================================

    /**
     * Table нэр зөвхөн a-z, 0-9, _, - тэмдэгтүүдийг зөвшөөрнө.
     *
     * @dataProvider maliciousTableNamesProvider
     */
    public function testTableNameSanitization(string $input, string $expected): void
    {
        $sanitized = \preg_replace('/[^A-Za-z0-9_-]/', '', $input);
        $this->assertSame($expected, $sanitized);
    }

    public static function maliciousTableNamesProvider(): array
    {
        return [
            'normal'           => ['dashboard', 'dashboard'],
            'with_underscore'  => ['dev_requests', 'dev_requests'],
            'injection_space'  => ['dashboard; DROP TABLE users', 'dashboardDROPTABLEusers'],
            'injection_quotes' => ["dashboard' OR '1'='1", 'dashboardOR11'],
            'injection_dots'   => ['dashboard.users', 'dashboardusers'],
            'empty_result'     => ["'; --", '--'],
        ];
    }

    // =========================================================================
    // Full flow: malicious CONTEXT бүрэн хаагдсан эсэх
    // =========================================================================

    /**
     * Бүрэн flow шалгалт: malicious CONTEXT field + value хослол.
     *
     * LogsController::retrieve() дотор ажиллах sanitization логикийг
     * бүрэн давтан шалгаж байна.
     */
    public function testFullSanitizationFlow(): void
    {
        $maliciousContext = [
            "') OR 1=1 --"                  => 'anything',
            'action'                         => "' OR '1'='1",
            "action UNION SELECT * FROM users" => 'test',
            'record_id'                      => '123',
            123                              => 'numeric_key',
            'valid.nested.field'             => 'safe_value',
        ];

        $fieldRegex = '/^[a-zA-Z0-9_.]+$/';
        $accepted = [];

        foreach ($maliciousContext as $field => $value) {
            if (!\is_string($value) || !\is_string($field)) {
                continue;
            }
            if (!\preg_match($fieldRegex, $field)) {
                continue;
            }
            $accepted[$field] = $value;
        }

        // Зөвхөн аюулгүй field-үүд үлдсэн
        $this->assertCount(3, $accepted);
        $this->assertArrayHasKey('action', $accepted);
        $this->assertArrayHasKey('record_id', $accepted);
        $this->assertArrayHasKey('valid.nested.field', $accepted);

        // SQL injection оролдлогууд хаагдсан
        $this->assertArrayNotHasKey("') OR 1=1 --", $accepted);
        $this->assertArrayNotHasKey("action UNION SELECT * FROM users", $accepted);
        $this->assertArrayNotHasKey(123, $accepted);

        // Value дотор injection байсан ч $this->quote() ашигладаг тул
        // string хэвээрээ дамжигдана (PDO::quote escape хийнэ)
        $this->assertSame("' OR '1'='1", $accepted['action']);
    }
}
