<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;

/**
 * PrivateFilesController::read() - blocked file extension/name test.
 *
 * Source code-d blockedExtensions, blockedFiles, .env* shalgalt
 * zuw bichigdsen esehiig batalgaajuulna.
 */
class PrivateFilesBlockedTest extends TestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = file_get_contents(
            dirname(__DIR__, 3) . '/application/raptor/content/file/PrivateFilesController.php'
        );
    }

    // =============================================
    // Blocked extensions
    // =============================================

    /**
     * @dataProvider blockedExtensionsProvider
     */
    public function testBlockedExtensionExists(string $ext): void
    {
        $this->assertStringContainsString(
            "'$ext'",
            self::$source,
            "Extension '$ext' should be in blockedExtensions list"
        );
    }

    public static function blockedExtensionsProvider(): array
    {
        return [
            'php'   => ['php'],
            'phtml' => ['phtml'],
            'phar'  => ['phar'],
            'sh'    => ['sh'],
            'bat'   => ['bat'],
            'cmd'   => ['cmd'],
            'exe'   => ['exe'],
            'ini'   => ['ini'],
            'log'   => ['log'],
            'sql'   => ['sql'],
        ];
    }

    // =============================================
    // Blocked file names
    // =============================================

    /**
     * @dataProvider blockedFilesProvider
     */
    public function testBlockedFileExists(string $file): void
    {
        $this->assertStringContainsString(
            "'$file'",
            self::$source,
            "File '$file' should be in blockedFiles list"
        );
    }

    public static function blockedFilesProvider(): array
    {
        return [
            '.env'           => ['.env'],
            '.htaccess'      => ['.htaccess'],
            '.htpasswd'      => ['.htpasswd'],
            '.gitignore'     => ['.gitignore'],
            'composer.json'  => ['composer.json'],
            'composer.lock'  => ['composer.lock'],
        ];
    }

    // =============================================
    // .env* prefix shalgalt
    // =============================================

    public function testEnvPrefixBlocked(): void
    {
        $this->assertStringContainsString(
            "str_starts_with(\$basename, '.env')",
            self::$source,
            'Should block all .env* files (e.g. .env.testing, .env.production)'
        );
    }

    // =============================================
    // blockedExtensions array baidag eseh
    // =============================================

    public function testBlockedExtensionsArrayExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$blockedExtensions\s*=\s*\[/',
            self::$source,
            'PrivateFilesController should have $blockedExtensions array'
        );
    }

    // =============================================
    // blockedFiles array baidag eseh
    // =============================================

    public function testBlockedFilesArrayExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$blockedFiles\s*=\s*\[/',
            self::$source,
            'PrivateFilesController should have $blockedFiles array'
        );
    }

    // =============================================
    // 403 Forbidden shiddag eseh
    // =============================================

    public function testThrowsForbiddenException(): void
    {
        $this->assertStringContainsString(
            "'Forbidden', 403",
            self::$source,
            'Should throw 403 Forbidden for blocked files'
        );
    }

    // =============================================
    // strtolower ashiglaj case-insensitive bolgoson eseh
    // =============================================

    public function testCaseInsensitiveCheck(): void
    {
        $this->assertStringContainsString(
            'strtolower',
            self::$source,
            'File extension/name check should be case-insensitive via strtolower'
        );
    }
}
