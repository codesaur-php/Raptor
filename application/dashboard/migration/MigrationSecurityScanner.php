<?php

namespace Dashboard\Migration;

use codesaur\DataObject\Constants;

/**
 * Class MigrationSecurityScanner
 *
 * Upload хийгдсэн migration SQL-ийг ажиллуулахаас өмнө static-ээр шалгана.
 * Auth/RBAC хүснэгтүүдэд хандах эрсдэлтэй pattern илэрвэл warning буцаана.
 *
 * Scanner нь "hard block" биш - system_coder нь "I understand" гэж confirm
 * бичсэн тохиолдолд apply хийгдэнэ. Зорилго нь санамсаргүй буюу мунхаг
 * privilege escalation хийгдэхээс сэргийлэх юм.
 *
 * @package Dashboard\Migration
 */
class MigrationSecurityScanner
{
    /**
     * Бичих эрхгүй (нөлөөлөл хязгаарлагдмал байх ёстой) хүснэгтүүд.
     */
    public const SENSITIVE_TABLES = [
        'users',
        'rbac_roles',
        'rbac_permissions',
        'rbac_user_role',
        'rbac_role_permission',
        'organizations',
        'organizations_users',
        'localization_language',
        'raptor_menu',
    ];

    /**
     * Хүснэгтийн нэрийн өмнөх optional schema prefix болон quote.
     *
     * MySQL backtick (`users`), PostgreSQL/ANSI double-quote ("users") болон
     * schema-qualified (public.users, `db`.`users`) нэрүүдийг ч таньдаг байх
     * ёстой - эс бөгөөс quote хийсэн нэрээр sensitive хүснэгт warning-гүй үлдэнэ.
     */
    private const Q = '(?:[`"]?\w+[`"]?\.)?[`"]?';

    /**
     * Pattern -> warning тайлбар.
     *
     * Бүх pattern нь case-insensitive (`/i` flag).
     * `\b` нь word boundary - `users` нь `usersx` гэх мэт нөгөө үгтэй хольж
     * илрэхгүй байх.
     * DML pattern-ууд `[^;]*` ашиглана (`.*` биш) - ингэснээр тааралт нэг
     * statement-ийн дотор л явагдаж, өөр statement дахь үг хольж false-match
     * хийхээс сэргийлнэ (жишээ: `UPDATE products; ... rbac_roles` хоёр statement).
     */
    private const PATTERNS = [
        '/\bUPDATE\s+(?:(?:LOW_PRIORITY|IGNORE|ONLY)\s+)*' . self::Q . 'users\b/i' => 'Modifies users table (potential password / role change)',
        '/\bINSERT\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*(?:INTO\s+)?' . self::Q . 'users\b/i' => 'Inserts user record',
        '/\bDELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+(?:ONLY\s+)?' . self::Q . 'users\b/i' => 'Deletes user record',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?' . self::Q . 'users\b/i' => 'Drops users table',
        '/\bTRUNCATE\s+(TABLE\s+)?' . self::Q . 'users\b/i' => 'Truncates users table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\brbac_/i'       => 'Modifies RBAC table (potential privilege escalation)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?' . self::Q . 'rbac_/i' => 'Drops RBAC table',
        '/\bTRUNCATE\s+(TABLE\s+)?' . self::Q . 'rbac_/i' => 'Truncates RBAC table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\borganizations(_users)?\b/i' => 'Modifies organizations table (multi-tenant boundary)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?' . self::Q . 'organizations(_users)?\b/i' => 'Drops organizations table',
        '/\bTRUNCATE\s+(TABLE\s+)?' . self::Q . 'organizations(_users)?\b/i' => 'Truncates organizations table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\blocalization_language\b/i' => 'Modifies localization_language table (site-wide locale impact)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?' . self::Q . 'localization_language\b/i' => 'Drops localization_language table',
        '/\bTRUNCATE\s+(TABLE\s+)?' . self::Q . 'localization_language\b/i' => 'Truncates localization_language table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\braptor_menu\b/i' => 'Modifies raptor_menu table (dashboard navigation)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?' . self::Q . 'raptor_menu\b/i' => 'Drops raptor_menu table',
        '/\bTRUNCATE\s+(TABLE\s+)?' . self::Q . 'raptor_menu\b/i' => 'Truncates raptor_menu table',

        '/\bGRANT\b/i'                                  => 'Grants database privileges',
        '/\bREVOKE\b/i'                                 => 'Revokes database privileges',
        '/\bCREATE\s+USER\b/i'                          => 'Creates database user',
        '/\bDROP\s+USER\b/i'                            => 'Drops database user',
        '/\bALTER\s+USER\b/i'                           => 'Alters database user',

        // CREATE TABLE is discouraged: Model classes auto-create their tables.
        // CREATE USER is matched above first; this pattern excludes that case via the negative lookahead.
        '/\bCREATE\s+(?!USER\b)(TEMPORARY\s+)?TABLE\b/i' => 'CREATE TABLE used - prefer defining a Model with setTable() instead; tables auto-create on first use',
    ];

    /**
     * PDO driver нэр (Constants::DRIVER_*). Quote-ийн утгыг тодорхойлно:
     * MySQL дээр "..." нь string literal (default sql_mode), PostgreSQL/SQLite
     * дээр identifier. null (тодорхойгүй) үед аюулгүй тал руу - "..." -г
     * identifier гэж үзэж агуулгыг нь шалгана.
     */
    private ?string $driver;

    /**
     * @param string|null $driver PDO driver нэр (`\PDO::ATTR_DRIVER_NAME`)
     */
    public function __construct(?string $driver = null)
    {
        $this->driver = $driver;
    }

    /**
     * SQL текстийг шалгах.
     *
     * Шалгахаасаа өмнө SQL comment (`--`, `/* * /`) болон string literal-уудыг
     * жинхэнэ executable код-тоо хольж pattern false-match-ээс сэргийлнэ.
     *
     * @param string $sql Бүхэл SQL агуулга (нэг файл = олон statement)
     *
     * @return array<int, array{level:string, reason:string}>
     *     Warning illerwel level='warning'-той array. Safe бол хоосон.
     */
    public function scan(string $sql): array
    {
        $sanitized = $this->stripCommentsAndStrings($sql);

        $warnings = [];
        foreach (self::PATTERNS as $pattern => $reason) {
            if (\preg_match($pattern, $sanitized)) {
                $warnings[] = [
                    'level'  => 'warning',
                    'reason' => $reason,
                ];
            }
        }

        return $warnings;
    }

    /**
     * Sanitized SQL: comment-ууд ба string literal-уудыг хоосон зайгаар сольсон
     * (identifier quote - backtick, pgsql-ийн "..." - агуулгаараа үлдэнэ).
     * Ингэснээр `'-- UPDATE users'` гэсэн string literal эсвэл
     * `-- comment with UPDATE users` нь pattern-д тохирохгүй.
     */
    private function stripCommentsAndStrings(string $sql): string
    {
        $out = '';
        $length = \strlen($sql);
        $i = 0;
        $mysql = $this->driver === Constants::DRIVER_MYSQL;

        while ($i < $length) {
            $ch = $sql[$i];

            // line comment: -- ... \n
            if ($ch === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                $end = \strpos($sql, "\n", $i);
                if ($end === false) {
                    break;
                }
                $out .= ' ';
                $i = $end + 1;
                continue;
            }

            // block comment: /* ... */
            if ($ch === '/' && $i + 1 < $length && $sql[$i + 1] === '*') {
                $end = \strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $out .= ' ';
                $i = $end + 2;
                continue;
            }

            // single-quoted string. Backslash escape (\') зөвхөн MySQL дээр
            // хүчинтэй - PostgreSQL (standard_conforming_strings) / SQLite дээр
            // backslash literal тул түүнийг escape гэж үзвэл string-ийн дараах
            // жинхэнэ код (`'a\'; UPDATE users ...`) нуугдана.
            if ($ch === '\'') {
                $i++;
                while ($i < $length) {
                    if ($mysql && $sql[$i] === '\\' && $i + 1 < $length) {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === '\'') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            // double-quoted: MySQL дээр string literal -> хоосолно.
            // PostgreSQL/SQLite (болон тодорхойгүй driver) дээр identifier
            // ("users") тул агуулгыг нь хадгалж pattern-д шалгуулна.
            //
            // Critical (English): on pgsql "..." is an identifier - stripping it
            // would hide `UPDATE "users"` from every pattern. Only MySQL treats
            // it as a string literal.
            if ($ch === '"') {
                $start = $i;
                $i++;
                while ($i < $length) {
                    if ($mysql && $sql[$i] === '\\' && $i + 1 < $length) {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === '"') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= $mysql ? ' ' : \substr($sql, $start, $i - $start);
                continue;
            }

            // backtick identifier (MySQL): агуулгыг хэвээр хадгална, гэхдээ
            // дотор нь байх ' эсвэл -- нь string/comment эхлүүлэхгүй.
            if ($ch === '`') {
                $end = \strpos($sql, '`', $i + 1);
                $end = $end === false ? $length : $end + 1;
                $out .= \substr($sql, $i, $end - $i);
                $i = $end;
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }
}
