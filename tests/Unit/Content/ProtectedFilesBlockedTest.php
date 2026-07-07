<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ProtectedFilesController::read() - blocked file extension/name test.
 *
 * Source code-д blockedExtensions, blockedFiles, .env* шалгалт
 * зөв бичигдсэн эсэхийг баталгаажуулна.
 */
class ProtectedFilesBlockedTest extends TestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = file_get_contents(
            dirname(__DIR__, 3) . '/application/dashboard/protected/ProtectedFilesController.php'
        );
    }

    // =============================================
    // Blocked extensions
    // =============================================

    /*     */
    #[DataProvider('blockedExtensionsProvider')]
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

    /*     */
    #[DataProvider('blockedFilesProvider')]
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
    // .env* prefix шалгалт
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
    // blockedExtensions array байдаг эсэх
    // =============================================

    public function testBlockedExtensionsArrayExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$blockedExtensions\s*=\s*\[/',
            self::$source,
            'ProtectedFilesController should have $blockedExtensions array'
        );
    }

    // =============================================
    // blockedFiles array байдаг эсэх
    // =============================================

    public function testBlockedFilesArrayExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$blockedFiles\s*=\s*\[/',
            self::$source,
            'ProtectedFilesController should have $blockedFiles array'
        );
    }

    // =============================================
    // 403 Forbidden шиддэг эсэх
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
    // strtolower ашиглаж case-insensitive болгосон эсэх
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
