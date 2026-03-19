<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;

/**
 * FileController-ийн pure logic тестүүд.
 *
 * convertPHPSizeToBytes(), formatSizeUnits(), allowImageOnly(),
 * allowCommonTypes(), uniqueName() зэрэг method-ыг шалгана.
 */
class FileControllerTest extends TestCase
{
    private static string $source;

    /** @var object FileController-ийн testable wrapper */
    private object $controller;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/file/FileController.php'
        );
    }

    protected function setUp(): void
    {
        // FileController extends Raptor\Controller which needs HTTP context.
        // We use ReflectionMethod to test protected/private methods directly
        // via a minimal anonymous subclass approach where possible,
        // or source-code analysis for methods that need full controller context.
    }

    // =============================================
    // convertPHPSizeToBytes() - ReflectionMethod
    // =============================================

    private function callConvertPHPSizeToBytes(string $size): int
    {
        // Create a mock that only uses the method we need
        $class = new \ReflectionClass(\Raptor\Content\FileController::class);
        $method = $class->getMethod('convertPHPSizeToBytes');

        // We need an instance - create via ReflectionClass without constructor
        $instance = $class->newInstanceWithoutConstructor();
        return $method->invoke($instance, $size);
    }

    /**
     * @dataProvider phpSizeBytesProvider
     */
    public function testConvertPHPSizeToBytes(string $input, int $expected): void
    {
        $this->assertSame($expected, $this->callConvertPHPSizeToBytes($input));
    }

    public static function phpSizeBytesProvider(): array
    {
        return [
            'plain bytes'   => ['4096', 4096],
            '1K'            => ['1K', 1024],
            '2M'            => ['2M', 2 * 1024 * 1024],
            '1G'            => ['1G', 1 * 1024 * 1024 * 1024],
            '128M'          => ['128M', 128 * 1024 * 1024],
            '32M'           => ['32M', 32 * 1024 * 1024],
            '500K'          => ['500K', 500 * 1024],
            'zero'          => ['0', 0],
            'lowercase k'   => ['1k', 1024],
            'lowercase m'   => ['2m', 2 * 1024 * 1024],
            'lowercase g'   => ['1g', 1 * 1024 * 1024 * 1024],
        ];
    }

    /**
     * T (terabyte) suffix шалгалт.
     */
    public function testConvertPHPSizeToBytesTerabyte(): void
    {
        $result = $this->callConvertPHPSizeToBytes('1T');
        $this->assertSame(1 * 1024 * 1024 * 1024 * 1024, $result);
    }

    /**
     * P (petabyte) suffix шалгалт.
     */
    public function testConvertPHPSizeToBytesPetabyte(): void
    {
        $result = $this->callConvertPHPSizeToBytes('1P');
        $this->assertSame(1 * 1024 * 1024 * 1024 * 1024 * 1024, $result);
    }

    // =============================================
    // formatSizeUnits()
    // =============================================

    private function callFormatSizeUnits(?int $bytes): string
    {
        $class = new \ReflectionClass(\Raptor\Content\FileController::class);
        $method = $class->getMethod('formatSizeUnits');
        $instance = $class->newInstanceWithoutConstructor();
        return $method->invoke($instance, $bytes);
    }

    /**
     * @dataProvider formatSizeProvider
     */
    public function testFormatSizeUnits(?int $bytes, string $expected): void
    {
        $this->assertSame($expected, $this->callFormatSizeUnits($bytes));
    }

    public static function formatSizeProvider(): array
    {
        return [
            'zero bytes'    => [0, '0b'],
            '500 bytes'     => [500, '500b'],
            '1 KB'          => [1024, '1.00kb'],
            '1 MB'          => [1048576, '1.00mb'],
            '1 GB'          => [1073741824, '1.00gb'],
            '1 TB'          => [1099511627776, '1.00tb'],
            '1023 bytes'    => [1023, '1023b'],
            '1.5 KB'        => [1536, '1.50kb'],
            '10 MB'         => [10485760, '10.00mb'],
            '2.5 GB'        => [2684354560, '2.50gb'],
        ];
    }

    /**
     * formatSizeUnits null input.
     */
    public function testFormatSizeUnitsNull(): void
    {
        // null should be treated as 0 or handled gracefully
        $result = $this->callFormatSizeUnits(null);
        $this->assertIsString($result);
    }

    // =============================================
    // allowImageOnly() - зөвхөн зургийн өргөтгөл
    // =============================================

    public function testAllowImageOnlyContainsCommonFormats(): void
    {
        $expected = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];
        foreach ($expected as $ext) {
            $this->assertStringContainsString(
                "'$ext'",
                self::$source,
                "allowImageOnly() must include $ext"
            );
        }
    }

    public function testAllowImageOnlyDoesNotContainDocuments(): void
    {
        // Extract allowImageOnly method body
        \preg_match('/function\s+allowImageOnly\s*\(\).*?\{(.+?)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'allowImageOnly() not found');
        $body = $m[1];

        $forbidden = ['pdf', 'doc', 'docx', 'mp4', 'zip', 'exe'];
        foreach ($forbidden as $ext) {
            $this->assertStringNotContainsString(
                "'$ext'",
                $body,
                "allowImageOnly() must NOT include $ext"
            );
        }
    }

    // =============================================
    // allowCommonTypes() - түгээмэл файл өргөтгөлүүд
    // =============================================

    /**
     * @dataProvider commonTypesProvider
     */
    public function testAllowCommonTypesContainsExtension(string $ext): void
    {
        \preg_match('/function\s+allowCommonTypes\s*\(\).*?\{(.+?)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'allowCommonTypes() not found');
        $this->assertStringContainsString("'$ext'", $m[1], "allowCommonTypes() must include $ext");
    }

    public static function commonTypesProvider(): array
    {
        return [
            // Images
            'jpg' => ['jpg'], 'png' => ['png'], 'gif' => ['gif'], 'webp' => ['webp'], 'svg' => ['svg'],
            // Documents
            'pdf' => ['pdf'], 'doc' => ['doc'], 'docx' => ['docx'], 'xls' => ['xls'], 'xlsx' => ['xlsx'],
            // Audio
            'mp3' => ['mp3'], 'wav' => ['wav'],
            // Video
            'mp4' => ['mp4'], 'webm' => ['webm'],
            // Archives
            'zip' => ['zip'], 'rar' => ['rar'], '7z' => ['7z'],
            // Text
            'txt' => ['txt'], 'json' => ['json'], 'xml' => ['xml'],
        ];
    }

    /**
     * allowCommonTypes() нь executable файл зөвшөөрөхгүй.
     */
    public function testAllowCommonTypesExcludesDangerousExts(): void
    {
        \preg_match('/function\s+allowCommonTypes\s*\(\).*?\{(.+?)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'allowCommonTypes() not found');
        $body = $m[1];

        $dangerous = ['php', 'exe', 'sh', 'bat', 'cmd', 'phar', 'sql'];
        foreach ($dangerous as $ext) {
            $this->assertStringNotContainsString(
                "'$ext'",
                $body,
                "allowCommonTypes() must NOT include dangerous extension $ext"
            );
        }
    }

    // =============================================
    // moveUploaded() - extension validation logic
    // =============================================

    public function testMoveUploadedUsesStrtolowerForExtension(): void
    {
        $this->assertStringContainsString(
            'strtolower',
            self::$source,
            'Extension check must be case-insensitive via strtolower'
        );
    }

    public function testMoveUploadedChecksUploadError(): void
    {
        $this->assertStringContainsString(
            'UPLOAD_ERR_OK',
            self::$source,
            'moveUploaded() must check for UPLOAD_ERR_OK'
        );
    }

    public function testMoveUploadedChecksSizeLimit(): void
    {
        $this->assertStringContainsString(
            '_size_limit',
            self::$source,
            'moveUploaded() must check file size against _size_limit'
        );
    }

    public function testMoveUploadedChecksAllowedExtensions(): void
    {
        $this->assertStringContainsString(
            '_allowed_exts',
            self::$source,
            'moveUploaded() must validate against _allowed_exts'
        );
    }

    // =============================================
    // uniqueName() - collision хамгаалалт
    // =============================================

    public function testUniqueNameMethodExists(): void
    {
        $this->assertStringContainsString(
            'function uniqueName',
            self::$source,
            'uniqueName() method must exist for filename collision protection'
        );
    }

    public function testUniqueNameUsesNumberSuffix(): void
    {
        // Should use _(N) pattern for collision avoidance
        $this->assertMatchesRegularExpression(
            '/\$name\s*\.\s*["\']_\(\$number\)\.["\']/',
            self::$source,
            'uniqueName() should use _(N) suffix pattern for collision avoidance'
        );
    }

    // =============================================
    // getMaximumFileUploadSize() - PHP config based
    // =============================================

    public function testGetMaximumFileUploadSizeUsesMin(): void
    {
        \preg_match('/function\s+getMaximumFileUploadSize.*?\{(.+?)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'getMaximumFileUploadSize() not found');
        $this->assertStringContainsString('min(', $m[1],
            'getMaximumFileUploadSize() must use min() of post_max_size and upload_max_filesize');
    }

    public function testGetMaximumFileUploadSizeChecksPostMaxSize(): void
    {
        $this->assertStringContainsString(
            'post_max_size',
            self::$source,
            'Must check post_max_size ini setting'
        );
    }

    public function testGetMaximumFileUploadSizeChecksUploadMaxFilesize(): void
    {
        $this->assertStringContainsString(
            'upload_max_filesize',
            self::$source,
            'Must check upload_max_filesize ini setting'
        );
    }

    // =============================================
    // optimizeImage() - зургийн optimize логик
    // =============================================

    public function testOptimizeImageChecksGdExtension(): void
    {
        $this->assertStringContainsString(
            "extension_loaded('gd')",
            self::$source,
            'optimizeImage() must check GD extension is loaded'
        );
    }

    public function testOptimizeImageSupportsJpegPngGifWebp(): void
    {
        $formats = ['IMAGETYPE_JPEG', 'IMAGETYPE_PNG', 'IMAGETYPE_GIF', 'IMAGETYPE_WEBP'];
        foreach ($formats as $format) {
            $this->assertStringContainsString(
                $format,
                self::$source,
                "optimizeImage() must support $format"
            );
        }
    }

    public function testOptimizeImageHandlesExifOrientation(): void
    {
        $this->assertStringContainsString(
            'exif_read_data',
            self::$source,
            'optimizeImage() must handle EXIF orientation for mobile photos'
        );
    }

    public function testOptimizeImagePreservesTransparency(): void
    {
        $this->assertStringContainsString(
            'imagesavealpha',
            self::$source,
            'optimizeImage() must preserve PNG/GIF transparency'
        );
    }

    public function testOptimizeImageUsesTemporaryFile(): void
    {
        $this->assertStringContainsString(
            ".tmp'",
            self::$source,
            'optimizeImage() must use temp file for size comparison before replacing'
        );
    }

    // =============================================
    // Path length protection
    // =============================================

    public function testMoveUploadedChecksPathLength(): void
    {
        \preg_match('/function\s+moveUploaded.*?\{(.+?)catch/s', self::$source, $m);
        $this->assertNotEmpty($m, 'moveUploaded() not found');
        $this->assertStringContainsString('max_name_length', $m[1],
            'moveUploaded() must check path length to fit VARCHAR(255)');
    }
}
