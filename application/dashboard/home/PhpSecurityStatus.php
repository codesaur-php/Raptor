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
     * Дистрибьюторын backport: багцын хувилбарын суурь (SRU дугааргүй) => засвартай
     * хамгийн бага багцын хувилбар. Ubuntu засварыг upstream-ийн шинэ хувилбар руу
     * шилжүүлэлгүй хуучин хувилбартаа нөхдөг (24.04: 8.3.6 хэвээр), тиймээс upstream
     * харьцуулалт засвартай серверийг эмзэг гэж дүгнэнэ. Debian засвартай upstream
     * хувилбар руу шилждэг (8.2.35-1~deb12u1) тул PATCHED-ээр зөв танигдана.
     * Эх сурвалж: дистрибьюторын аюулгүй байдлын мэдэгдэл (энд USN-8948-1).
     */
    public const BACKPORTS = [
        '8.1.2-1ubuntu2' => '8.1.2-1ubuntu2.28',             // Ubuntu 22.04 LTS
        '8.3.6-0ubuntu0.24.04' => '8.3.6-0ubuntu0.24.04.13', // Ubuntu 24.04 LTS
        '8.5.4-0ubuntu1' => '8.5.4-0ubuntu1.5',              // Ubuntu 26.04 LTS
        '8.5.9-0ubuntu2' => '8.5.9-0ubuntu2.1'               // Ubuntu 26.10
    ];

    /**
     * @param string $version PHP хувилбар (дистрибьюторын build-д багцын хувилбар залгагдсан байж болно)
     * @param string|null $package Дистрибьюторын багцын хувилбар; null бол $version-ийн залгаас,
     *     эсвэл dpkg-ийн бүртгэлээс хайна ('' - хайхгүй)
     * @return array{version:string, patched:bool, required:?string, advisory:string,
     *     package:?string, backport:bool,
     *     sapi:string, ini:array<string, array{value:string, required:string, ok:bool}>,
     *     mitigated:bool, ini_file:?string, user_ini:?string}
     */
    public static function check(string $version = \PHP_VERSION, ?string $package = null): array
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
        $backport = false;
        if (!$patched) {
            $package ??= $clean !== $version ? $version : self::installedPackage($branch);
            /* Ажиллаж буй upstream хувилбарын багц биш бол (өөр PHP) тооцохгүй */
            if ($package !== null && $package !== '' && \str_starts_with($package, "$clean-")) {
                $fixed = self::backportFixed($package);
                if ($fixed !== null) {
                    $backport = $patched = self::revision($package, $fixed) >= 0;
                    $required = $fixed;
                }
            }
        }
        return [
            'version' => $version,
            'patched' => $patched,
            'required' => $required,
            'advisory' => self::ADVISORY,
            'package' => $package === '' ? null : $package,
            'backport' => $backport
        ] + self::runtime();
    }

    /**
     * Багцын хувилбарт тохирох BACKPORTS-ийн засвартай хувилбар (суурь нь яг таарах
     * эсвэл "." + SRU дугаараар үргэлжлэх ёстой - 1ubuntu2 нь 1ubuntu20-тэй таарахгүй).
     */
    public static function backportFixed(string $package): ?string
    {
        foreach (self::BACKPORTS as $base => $fixed) {
            if ($package === $base || \str_starts_with($package, "$base.")) {
                return $fixed;
            }
        }
        return null;
    }

    /**
     * Ижил суурьтай хоёр багцын SRU дугаарыг тоогоор харьцуулна. "+esm1" зэрэг
     * залгаасыг үл тооно (version_compare үүнийг хуучин гэж үздэг).
     */
    private static function revision(string $package, string $fixed): int
    {
        $base = (string)\array_search($fixed, self::BACKPORTS, true);
        $sru = static fn(string $v): string =>
            \preg_match('/^\.(\d+(?:\.\d+)*)/', \substr($v, \strlen($base)), $m) ? $m[1] : '0';
        return \version_compare($sru($package), $sru($fixed));
    }

    /**
     * PHP_VERSION-д багцын хувилбар залгагдаагүй Debian/Ubuntu build-ийн хувьд
     * энэ SAPI-ийн багцын суулгасан хувилбарыг dpkg-ийн бүртгэлээс уншина.
     * Shared hosting / Windows / open_basedir хязгаартай үед null.
     * Суулгасан багц нь PHP процессыг restart хийтэл ажиллаж буй кодоос ялгаатай байж болно.
     */
    private static function installedPackage(string $branch): ?string
    {
        $name = match (\PHP_SAPI) {
            'apache2handler' => "libapache2-mod-php$branch",
            'fpm-fcgi' => "php$branch-fpm",
            'cgi-fcgi' => "php$branch-cgi",
            'cli' => "php$branch-cli",
            default => null
        };
        $file = '/var/lib/dpkg/status';
        if ($name === null || !@\is_readable($file) || !($handle = @\fopen($file, 'r'))) {
            return null;
        }
        $found = $installed = false;
        $version = null;
        while (($line = \fgets($handle)) !== false) {
            $line = \rtrim($line);
            if ($line === '') {
                if ($found) {
                    break;
                }
                continue;
            }
            if (\str_starts_with($line, 'Package: ')) {
                $found = \substr($line, 9) === $name;
            } elseif ($found && \str_starts_with($line, 'Status: ')) {
                $installed = \str_ends_with($line, ' installed');
            } elseif ($found && \str_starts_with($line, 'Version: ')) {
                $version = \substr($line, 9);
            }
        }
        \fclose($handle);
        return $found && $installed ? $version : null;
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
