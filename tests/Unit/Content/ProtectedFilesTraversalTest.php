<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ProtectedFilesController::read() - directory traversal containment test.
 *
 * Хоёр төрлийн шалгалт:
 *  1. Source code-д realpath-д суурилсан containment guard бичигдсэн эсэх.
 *  2. Behavioral: realpath + str_starts_with алгоритм ?name=/../.. зэрэг
 *     payload-ийг үнэхээр protected хавтаснаас гарахгүй байлгаж байгаа эсэх
 *     (жинхэнэ file system дээр).
 */
class ProtectedFilesTraversalTest extends TestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/file/ProtectedFilesController.php'
        );
    }

    // =============================================
    // Source-code guard шалгалт
    // =============================================

    public function testReadUsesRealpathForContainment(): void
    {
        $this->assertStringContainsString('\realpath($protectedDir)', self::$source,
            'read() must canonicalize the protected dir with realpath');
        $this->assertStringContainsString('\realpath($filePath)', self::$source,
            'read() must canonicalize the requested file with realpath');
    }

    public function testReadEnforcesPrefixWithSeparator(): void
    {
        // realFile-ийн protected dir + DIRECTORY_SEPARATOR-ээр эхэлж байх ёстой.
        // Separator-гүй бол /protected-evil гэх мэт prefix давхцал гарна.
        $this->assertMatchesRegularExpression(
            '/str_starts_with\(\s*\$realFile\s*,\s*\$realProtected\s*\.\s*\\\\DIRECTORY_SEPARATOR\s*\)/',
            self::$source,
            'read() must require realFile to start with protected dir + DIRECTORY_SEPARATOR'
        );
    }

    public function testReadRejectsWhenRealpathFails(): void
    {
        $this->assertStringContainsString('$realProtected === false', self::$source,
            'read() must treat unresolvable protected dir as forbidden');
        $this->assertStringContainsString('$realFile === false', self::$source,
            'read() must treat unresolvable file path as forbidden');
    }

    public function testTraversalGuardThrows403(): void
    {
        // Guard нь containment алдагдвал 403 Forbidden шиднэ.
        \preg_match('/function\s+read\s*\(\).*?\{(.+)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'read() method not found');
        $this->assertStringContainsString("'Forbidden', 403", $m[1],
            'traversal guard must throw 403 Forbidden');
    }

    // =============================================
    // Behavioral: жинхэнэ алгоритм жинхэнэ FS дээр
    // =============================================

    /**
     * read()-ийн containment логикийг хуулбарлаж, тохиолдол болгоныг сорино.
     * Буцаах утга: хамгаалалттай (allowed) бол true.
     */
    private static function isAllowed(string $protectedDir, string $name): bool
    {
        if ($name === '') {
            return false;
        }
        $realProtected = \realpath($protectedDir);
        $realFile = \realpath($protectedDir . $name); // getDocumentPath() шиг зүгээр холбоно
        if ($realFile === false || !\is_file($realFile)) {
            return false; // 404 - байхгүй эсвэл хавтас
        }
        if ($realProtected === false
            || !\str_starts_with($realFile, $realProtected . \DIRECTORY_SEPARATOR)
        ) {
            return false; // 403
        }
        return true;
    }

    private string $base = '';
    private string $protected = '';

    protected function setUp(): void
    {
        $this->base = \sys_get_temp_dir() . '/raptor_trav_' . \uniqid();
        $this->protected = $this->base . '/protected';
        \mkdir($this->protected . '/sub', 0777, true);
        \file_put_contents($this->protected . '/ok.txt', 'public-ish');
        \file_put_contents($this->protected . '/sub/deep.txt', 'deep');
        // protected-аас гадуур байгаа нууц file
        \file_put_contents($this->base . '/secret.txt', 'TOP SECRET');
        \mkdir($this->base . '/somesecretfolder', 0777, true);
        \file_put_contents($this->base . '/somesecretfolder/dangerinfo.txt', 'danger');
        // /protected-evil prefix давхцалын кейс
        \mkdir($this->base . '/protected-evil', 0777, true);
        \file_put_contents($this->base . '/protected-evil/x.txt', 'evil');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = "$dir/$f";
            \is_dir($p) ? $this->rrmdir($p) : \unlink($p);
        }
        \rmdir($dir);
    }

    #[DataProvider('legitProvider')]
    public function testLegitFilesAllowed(string $name): void
    {
        $this->assertTrue(
            self::isAllowed($this->protected, $name),
            "Legit protected file '$name' should be served"
        );
    }

    public static function legitProvider(): array
    {
        return [
            'root file'   => ['/ok.txt'],
            'nested file' => ['/sub/deep.txt'],
        ];
    }

    #[DataProvider('traversalProvider')]
    public function testTraversalBlocked(string $name): void
    {
        $this->assertFalse(
            self::isAllowed($this->protected, $name),
            "Traversal payload '$name' must be blocked (escapes /protected)"
        );
    }

    public static function traversalProvider(): array
    {
        return [
            // хэрэглэгчийн жишээ
            'user example'      => ['/../../../somesecretfolder/dangerinfo.txt'],
            'parent secret'     => ['/../secret.txt'],
            'single up'         => ['/../somesecretfolder/dangerinfo.txt'],
            'nested then up'    => ['/sub/../../secret.txt'],
            'prefix sibling'    => ['/../protected-evil/x.txt'],
            'empty name'        => [''],
            'nonexistent'       => ['/nope.txt'],
        ];
    }
}
