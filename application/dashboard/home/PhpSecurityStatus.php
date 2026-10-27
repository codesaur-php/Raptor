<?php

namespace Dashboard\Home;

/**
 * Class PhpSecurityStatus
 * ------------------------------------------------------------------
 * Сервер дээр ажиллаж буй PHP хувилбар мэдэгдэж буй ноцтой эмзэг байдлын
 * засвартай эсэх, засвар хүлээх хугацаанд хэрэглэх түр хамгаалалтын
 * тохиргоо энэ серверт үйлчилж байгаа эсэхийг шалгана.
 *
 * Shared hosting дээр бүтэн хувилбар (8.3.xx) ихэвчлэн харагддаггүй, VPS /
 * localhost дээр CLI-ийн `php -v` нь вэб серверийн PHP-ээс өөр байж болно.
 * Тиймээс вэб хүсэлтэд үйлчилж буй PHP-ийн бодит утгыг dashboard-ийн нүүр
 * хуудсанд зөвхөн system_coder-т харуулна (HomeController, home.html).
 *
 * Шинэ эмзэг байдал зарлагдвал ADVISORY, PATCHED, MITIGATION-ийг шинэчилнэ.
 * Тохиргоог серверт хэрхэн хэрэгжүүлэхийг docs/{en,mn}/PHP-SECURITY.md-ээс үз.
 *
 * @package Dashboard\Home
 */
final class PhpSecurityStatus
{
    /** Эмзэг байдлын нэр (мэдэгдэлд харагдана). */
    public const ADVISORY = 'PHP2Shell (CVE-2026-109618, CVE-2026-109619)';

    /** Салбар бүрийн засвартай хамгийн бага хувилбар; жагсаалтаас хуучин салбар засвар авахгүй (эмзэг). */
    public const PATCHED = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];

    /** Түр хамгаалалт: ini тохиргоо => шаардлагатай утга. Бүгд таарвал mitigated. */
    public const MITIGATION = ['session.upload_progress.enabled' => 'Off', 'memory_limit' => '-1'];

    /**
     * @return array{version:string, patched:bool, required:?string, advisory:string,
     *     sapi:string, ini:array<string, array{value:string, required:string, ok:bool}>,
     *     mitigated:bool, ini_file:?string, user_ini:?string}
     */
    public static function check(string $version = \PHP_VERSION): array
    {
        $clean = \preg_replace('/[^0-9.].*$/', '', $version);
        $branch = \implode('.', \array_slice(\explode('.', $clean), 0, 2));
        if (isset(self::PATCHED[$branch])) {
            $required = self::PATCHED[$branch];
            $patched = \version_compare($clean, $required, '>=');
        } else {
            /* Жагсаалтаас шинэ салбар засвартай гарсан; хуучин салбар засвар авахгүй */
            $patched = \version_compare($branch, \array_key_last(self::PATCHED), '>');
            $required = $patched ? null : self::PATCHED[\array_key_first(self::PATCHED)];
        }
        return [
            'version' => $version,
            'patched' => $patched,
            'required' => $required,
            'advisory' => self::ADVISORY
        ] + self::runtime();
    }

    /**
     * Энэ HTTP хүсэлтэд үйлчилж буй PHP-ийн ажиллах горим (SAPI), түр хамгаалалтын
     * тохиргооны бодит утга, ачаалагдсан php.ini болон хэрэглэгчийн ini файлын нэр.
     * SAPI нь тохиргоог хаана хийхийг заана (apache2handler - php.ini / Apache config,
     * fpm-fcgi - FPM pool, cgi-fcgi / litespeed - php.ini / .user.ini).
     *
     * @return array{sapi:string, ini:array<string, array{value:string, required:string, ok:bool}>,
     *     mitigated:bool, ini_file:?string, user_ini:?string}
     */
    public static function runtime(): array
    {
        $ini = [];
        foreach (self::MITIGATION as $key => $required) {
            $value = \ini_get($key);
            $value = $value === false ? '-' : (string)$value;
            $ini[$key] = ['value' => $value, 'required' => $required, 'ok' => self::iniMatches($value, $required)];
        }
        $loaded = \php_ini_loaded_file();
        $userIni = \ini_get('user_ini.filename');
        return [
            'sapi' => \PHP_SAPI,
            'ini' => $ini,
            'mitigated' => !\in_array(false, \array_column($ini, 'ok'), true),
            'ini_file' => $loaded === false ? null : $loaded,
            'user_ini' => $userIni === false || $userIni === '' ? null : $userIni
        ];
    }

    /**
     * ini утгыг шаардлагатай утгатай харьцуулна. On/Off төрлийн утгыг
     * php.ini-ийн бичлэгээс үл хамааран ("", "0", "off" гэх мэт) ижилд тооцно.
     */
    public static function iniMatches(string $actual, string $required): bool
    {
        $bool = static fn(string $v): ?bool => match (\strtolower(\trim($v))) {
            '', '0', 'off', 'false', 'no', 'none' => false,
            '1', 'on', 'true', 'yes' => true,
            default => null
        };
        $want = $bool($required);
        if ($want !== null) {
            return $bool($actual) === $want;
        }
        return \trim($actual) === $required;
    }
}
