<?php

namespace Raptor\Migration;

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
 * @package Raptor\Migration
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
        '/\bUPDATE\s+users\b/i'                         => 'Modifies users table (potential password / role change)',
        '/\bINSERT\s+INTO\s+users\b/i'                  => 'Inserts user record',
        '/\bDELETE\s+FROM\s+users\b/i'                  => 'Deletes user record',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?users\b/i'  => 'Drops users table',
        '/\bTRUNCATE\s+(TABLE\s+)?users\b/i'            => 'Truncates users table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\brbac_/i'       => 'Modifies RBAC table (potential privilege escalation)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?rbac_/i'    => 'Drops RBAC table',
        '/\bTRUNCATE\s+(TABLE\s+)?rbac_/i'              => 'Truncates RBAC table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\borganizations(_users)?\b/i' => 'Modifies organizations table (multi-tenant boundary)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?organizations(_users)?\b/i' => 'Drops organizations table',
        '/\bTRUNCATE\s+(TABLE\s+)?organizations(_users)?\b/i' => 'Truncates organizations table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\blocalization_language\b/i' => 'Modifies localization_language table (site-wide locale impact)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?localization_language\b/i' => 'Drops localization_language table',
        '/\bTRUNCATE\s+(TABLE\s+)?localization_language\b/i' => 'Truncates localization_language table',

        '/\b(INSERT|UPDATE|DELETE)[^;]*\braptor_menu\b/i' => 'Modifies raptor_menu table (dashboard navigation)',
        '/\bDROP\s+TABLE\s+(IF\s+EXISTS\s+)?raptor_menu\b/i' => 'Drops raptor_menu table',
        '/\bTRUNCATE\s+(TABLE\s+)?raptor_menu\b/i'       => 'Truncates raptor_menu table',

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
     * Sanitized SQL: comment-ууд ба string literal-уудыг хоосон зайгаар сольсон.
     * Ингэснээр `'-- UPDATE users'` гэсэн string literal эсвэл
     * `-- comment with UPDATE users` нь pattern-д тохирохгүй.
     */
    private function stripCommentsAndStrings(string $sql): string
    {
        $out = '';
        $length = \strlen($sql);
        $i = 0;

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

            // single-quoted string
            if ($ch === '\'') {
                $i++;
                while ($i < $length) {
                    if ($sql[$i] === '\\' && $i + 1 < $length) {
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

            // double-quoted string / identifier
            if ($ch === '"') {
                $i++;
                while ($i < $length) {
                    if ($sql[$i] === '\\' && $i + 1 < $length) {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === '"') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }
}
