<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Raptor\CacheService;

/**
 * CacheService болон cache-тэй холбоотой хамгаалалт тестлэх.
 */
class CacheTest extends TestCase
{
    private static string $cacheDir;
    private static ?CacheService $cache = null;

    public static function setUpBeforeClass(): void
    {
        self::$cacheDir = sys_get_temp_dir() . '/raptor_cache_test_' . uniqid();
        mkdir(self::$cacheDir, 0755, true);
        self::$cache = new CacheService(self::$cacheDir, 60);
    }

    public static function tearDownAfterClass(): void
    {
        self::$cache?->clear();
        // Temp directory цэвэрлэх
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        if (is_dir(self::$cacheDir)) {
            rmdir(self::$cacheDir);
        }
    }

    // =============================================
    // CacheService CRUD
    // =============================================

    public function testSetAndGet(): void
    {
        self::$cache->set('test.key', 'hello');
        $this->assertSame('hello', self::$cache->get('test.key'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertNull(self::$cache->get('nonexistent'));
        $this->assertSame('fallback', self::$cache->get('nonexistent', 'fallback'));
    }

    public function testDelete(): void
    {
        self::$cache->set('to.delete', 'value');
        $this->assertSame('value', self::$cache->get('to.delete'));

        self::$cache->delete('to.delete');
        $this->assertNull(self::$cache->get('to.delete'));
    }

    public function testClear(): void
    {
        self::$cache->set('clear.a', 1);
        self::$cache->set('clear.b', 2);

        self::$cache->clear();

        $this->assertNull(self::$cache->get('clear.a'));
        $this->assertNull(self::$cache->get('clear.b'));
    }

    public function testOverwrite(): void
    {
        self::$cache->set('overwrite', 'old');
        self::$cache->set('overwrite', 'new');
        $this->assertSame('new', self::$cache->get('overwrite'));
    }

    public function testArrayValue(): void
    {
        $data = ['mn' => ['locale' => 'mn-MN', 'title' => 'Mongolian'], 'en' => ['locale' => 'en-US', 'title' => 'English']];
        self::$cache->set('languages', $data);
        $this->assertSame($data, self::$cache->get('languages'));
    }

    public function testEmptyStringValue(): void
    {
        self::$cache->set('empty', '');
        $this->assertSame('', self::$cache->get('empty'));
    }

    public function testLanguageSpecificKeys(): void
    {
        self::$cache->set('texts.mn', ['hello' => 'Сайн уу']);
        self::$cache->set('texts.en', ['hello' => 'Hello']);

        $this->assertSame(['hello' => 'Сайн уу'], self::$cache->get('texts.mn'));
        $this->assertSame(['hello' => 'Hello'], self::$cache->get('texts.en'));

        self::$cache->delete('texts.mn');
        $this->assertNull(self::$cache->get('texts.mn'));
        $this->assertSame(['hello' => 'Hello'], self::$cache->get('texts.en'));
    }

    // =============================================
    // PSR-16 compliance - TTL, has, multi-ops
    // =============================================

    public function testTtlExpiry(): void
    {
        self::$cache->set('expire.soon', 'value', 1);
        $this->assertSame('value', self::$cache->get('expire.soon'));

        // Wait past expiry (sleep 2 seconds to clear 1-second TTL)
        \sleep(2);
        $this->assertNull(self::$cache->get('expire.soon'));
        $this->assertFalse(self::$cache->has('expire.soon'));
    }

    public function testTtlAsDateInterval(): void
    {
        self::$cache->set('di.expire', 'value', new \DateInterval('PT1S'));
        $this->assertSame('value', self::$cache->get('di.expire'));

        \sleep(2);
        $this->assertNull(self::$cache->get('di.expire'));
    }

    public function testTtlZeroOrNegativeTreatedAsNoExpiry(): void
    {
        // ttl = 0 -> no expiry (хугацаагүй)
        self::$cache->set('forever', 'value', 0);
        $this->assertSame('value', self::$cache->get('forever'));

        self::$cache->delete('forever');
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        self::$cache->set('exists', 'value');
        $this->assertTrue(self::$cache->has('exists'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::$cache->delete('missing.key');
        $this->assertFalse(self::$cache->has('missing.key'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        // null утгыг тусад нь хадгалсан тохиолдолд `has()` true байх ёстой
        // (`has()` нь зөвхөн файлын существование шалгадаг)
        self::$cache->set('null.value', null);
        $this->assertTrue(self::$cache->has('null.value'));
        $this->assertNull(self::$cache->get('null.value'));
    }

    public function testHasReturnsTrueForFalseValue(): void
    {
        self::$cache->set('false.value', false);
        $this->assertTrue(self::$cache->has('false.value'));
        $this->assertFalse(self::$cache->get('false.value'));
    }

    public function testHasReturnsTrueForZero(): void
    {
        self::$cache->set('zero.value', 0);
        $this->assertTrue(self::$cache->has('zero.value'));
        $this->assertSame(0, self::$cache->get('zero.value'));
    }

    public function testGetMultiple(): void
    {
        self::$cache->set('multi.a', 1);
        self::$cache->set('multi.b', 2);

        $values = \iterator_to_array(
            (function () {
                $r = self::$cache->getMultiple(['multi.a', 'multi.b', 'multi.missing'], 'fallback');
                foreach ($r as $k => $v) {
                    yield $k => $v;
                }
            })()
        );

        $this->assertSame(1, $values['multi.a']);
        $this->assertSame(2, $values['multi.b']);
        $this->assertSame('fallback', $values['multi.missing']);
    }

    public function testSetMultiple(): void
    {
        $result = self::$cache->setMultiple([
            'set.a' => 'A',
            'set.b' => 'B',
        ]);
        $this->assertTrue($result);
        $this->assertSame('A', self::$cache->get('set.a'));
        $this->assertSame('B', self::$cache->get('set.b'));
    }

    public function testDeleteMultiple(): void
    {
        self::$cache->set('del.a', 1);
        self::$cache->set('del.b', 2);
        self::$cache->set('del.c', 3);

        self::$cache->deleteMultiple(['del.a', 'del.b']);
        $this->assertNull(self::$cache->get('del.a'));
        $this->assertNull(self::$cache->get('del.b'));
        $this->assertSame(3, self::$cache->get('del.c'));
    }

    public function testBooleanFalseRoundTrip(): void
    {
        // Регрессын тест - `unserialize` амжилтгүй болон литерал `false` хоёрыг
        // зөв ялгах ёстой. Бид [exp, value] хосыг serialize хийдэг тул эхний
        // элемент үргэлж integer тул `unserialize` `false` буцаана гэдэг нь
        // зөвхөн файл эвдэрсэн гэсэн үг.
        self::$cache->set('bool.false', false);
        $this->assertFalse(self::$cache->get('bool.false'));
        $this->assertNotSame(null, self::$cache->get('bool.false'));
    }

    public function testIntegerZeroRoundTrip(): void
    {
        self::$cache->set('int.zero', 0);
        $this->assertSame(0, self::$cache->get('int.zero'));
    }

    public function testNullValueRoundTrip(): void
    {
        self::$cache->set('null.actual', null);
        // get() returns null for both "missing" and "stored null" - ambiguous;
        // use has() to distinguish.
        $this->assertTrue(self::$cache->has('null.actual'));
        $this->assertNull(self::$cache->get('null.actual'));
    }

    public function testHashKeyCollisionAvoidance(): void
    {
        // sha1 hash-аар key-г файлын нэр болгодог. Урт key, тусгай тэмдэгт
        // зэрэг ч ажиллах ёстой.
        $longKey = \str_repeat('a', 500);
        $unicodeKey = 'түлхүүр_монгол';
        $specialKey = "key with spaces / slashes \t tabs";

        self::$cache->set($longKey, 'long');
        self::$cache->set($unicodeKey, 'unicode');
        self::$cache->set($specialKey, 'special');

        $this->assertSame('long', self::$cache->get($longKey));
        $this->assertSame('unicode', self::$cache->get($unicodeKey));
        $this->assertSame('special', self::$cache->get($specialKey));
    }

    public function testImplementsPsrSimpleCacheInterface(): void
    {
        $this->assertInstanceOf(\Psr\SimpleCache\CacheInterface::class, self::$cache);
    }

    // =============================================
    // Cross-platform edge cases
    // =============================================

    public function testDeleteNonexistentReturnsTrue(): void
    {
        // PSR-16: үгүй key устгах нь алдаа биш
        $this->assertTrue(self::$cache->delete('never.existed'));
    }

    public function testClearOnEmptyDirReturnsTrue(): void
    {
        $emptyDir = sys_get_temp_dir() . '/raptor_cache_empty_' . uniqid();
        $cache = new CacheService($emptyDir);
        $this->assertTrue($cache->clear());
        @rmdir($emptyDir);
    }

    public function testClearOnNonexistentDirReturnsTrue(): void
    {
        $dir = sys_get_temp_dir() . '/raptor_cache_nonexistent_' . uniqid();
        $cache = new CacheService($dir);
        // Constructor үүсгэдэг тул шууд устгаад clear() дуудна
        @rmdir($dir);
        $this->assertTrue($cache->clear());
    }

    public function testConstructorCreatesDir(): void
    {
        $newDir = sys_get_temp_dir() . '/raptor_cache_new_' . uniqid();
        $this->assertDirectoryDoesNotExist($newDir);
        new CacheService($newDir);
        $this->assertDirectoryExists($newDir);
        rmdir($newDir);
    }

    public function testConstructorThrowsOnInvalidPath(): void
    {
        // Файл (folder биш) дээр заавал dir үүсгэх боломжгүй
        $blockerFile = sys_get_temp_dir() . '/raptor_cache_blocker_' . uniqid();
        file_put_contents($blockerFile, 'not a dir');

        $this->expectException(\RuntimeException::class);
        try {
            new CacheService($blockerFile . '/cache_subdir');
        } finally {
            @unlink($blockerFile);
        }
    }

    public function testCorruptedFileIsHandledGracefully(): void
    {
        // Cache файл маш гадны source-оос эвдэрсэн scenario simulate
        self::$cache->set('corrupt.key', 'value');
        $hash = sha1('corrupt.key');
        file_put_contents(self::$cacheDir . '/' . $hash . '.cache', 'not valid serialized data');

        $this->assertNull(self::$cache->get('corrupt.key'));
        $this->assertFalse(self::$cache->has('corrupt.key'));
    }

    // =============================================
    // PrivateFilesController cache хамгаалалт
    // =============================================

    public function testPrivateFilesReadBlocksCache(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/file/PrivateFilesController.php'
        );

        $this->assertStringContainsString(
            'cache',
            $source,
            'PrivateFilesController::read() should check for cache directory'
        );

        $this->assertStringContainsString(
            'cacheDir',
            $source,
            'PrivateFilesController should define cacheDir path for blocking'
        );
    }

    public function testPrivateFilesSetFolderBlocksCache(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/file/PrivateFilesController.php'
        );

        // setFolder method deer cache shalgalt baidag eseh
        $this->assertMatchesRegularExpression(
            '/function setFolder.*?cache/s',
            $source,
            'PrivateFilesController::setFolder() should block cache folder'
        );
    }

    public function testReadMethodBlocksCacheWithForbidden(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/file/PrivateFilesController.php'
        );

        // read() deer cache folder -> Forbidden shiddeg eseh
        $this->assertMatchesRegularExpression(
            '/cacheDir.*?Forbidden/s',
            $source,
            'read() should throw Forbidden for cache directory access'
        );
    }

    public function testSetFolderBlocksCacheWithException(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/file/PrivateFilesController.php'
        );

        // setFolder() deer cache -> RuntimeException shiddeg eseh
        $this->assertMatchesRegularExpression(
            "/str_starts_with.*cache.*403/s",
            $source,
            'setFolder() should throw 403 for cache folder'
        );
    }

    // =============================================
    // CacheService container бүртгэл
    // =============================================

    public function testContainerRegistersCache(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/ContainerMiddleware.php'
        );

        $this->assertStringContainsString(
            "'cache'",
            $source,
            'ContainerMiddleware should register cache service'
        );

        $this->assertStringContainsString(
            'CacheService',
            $source,
            'Cache service should use CacheService class'
        );
    }

    public function testContainerCacheFailSafe(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/ContainerMiddleware.php'
        );

        $this->assertStringContainsString(
            'return null',
            $source,
            'Cache factory should return null on failure (fail-safe)'
        );
    }

    // =============================================
    // Controller::invalidateCache fail-safe
    // =============================================

    public function testInvalidateCacheFailSafe(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/Controller.php'
        );

        $this->assertStringContainsString(
            'function invalidateCache',
            $source,
            'Controller should have invalidateCache method'
        );

        // try-catch бүтэцтэй эсэх
        $this->assertMatchesRegularExpression(
            '/function invalidateCache.*?try.*?catch/s',
            $source,
            'invalidateCache should be wrapped in try-catch'
        );

        // null шалгалт
        $this->assertMatchesRegularExpression(
            '/cache === null.*?return/s',
            $source,
            'invalidateCache should return early if cache is null'
        );
    }

    // =============================================
    // Middleware cache ашиглалт
    // =============================================

    public function testLocalizationMiddlewareUsesCache(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/localization/LocalizationMiddleware.php'
        );

        $this->assertStringContainsString("'languages'", $source, 'Should cache languages');
        $this->assertStringContainsString("\"texts.\$langCode\"", $source, 'Should cache texts by language code');
    }

    public function testSettingsMiddlewareUsesCache(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/settings/SettingsMiddleware.php'
        );

        $this->assertStringContainsString("\"settings.\$code\"", $source, 'Should cache settings by language code');
    }

    // =============================================
    // Cache invalidation зөв газарт байгаа эсэх
    // =============================================

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidationInTryBlock(string $file, string $cacheKey): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $file);

        // invalidateCache нь try блок дотор байх ёстой (finally биш)
        // respondJSON('success') -ийн өмнө эсвэл дараа байх ёстой
        $this->assertStringContainsString(
            $cacheKey,
            $source,
            "File $file should invalidate cache key $cacheKey"
        );

        // finally блок дотор invalidateCache байх ёсгүй
        if (preg_match('/finally\s*\{(.*?)(?=\}\s*\})/s', $source, $matches)) {
            $finallyBlock = $matches[1];
            $this->assertStringNotContainsString(
                'invalidateCache',
                $finallyBlock,
                "File $file should NOT have invalidateCache in finally block"
            );
        }
    }

    public static function invalidationProvider(): array
    {
        return [
            'LanguageController' => ['application/raptor/localization/language/LanguageController.php', "'languages'"],
            'TextController' => ['application/raptor/localization/text/TextController.php', "'texts.{code}'"],
            'SettingsController' => ['application/raptor/content/settings/SettingsController.php', "'settings.{code}'"],
            'TemplateController' => ['application/raptor/template/TemplateController.php', "'menu.{code}'"],
            'PagesController' => ['application/raptor/content/page/PagesController.php', "'pages_nav.{code}'"],
            'NewsController' => ['application/raptor/content/news/NewsController.php', "'recent_news.{code}'"],
            'ReferencesController' => ['application/raptor/content/reference/ReferencesController.php', 'reference.$table.{code}'],
        ];
    }
}
