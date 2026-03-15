<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;

/**
 * Кодын чанарын тест - бидний хийсэн засваруудыг source level-д шалгах.
 *
 * - throw new \Error → throw new \Exception болсон эсэх
 * - htmlspecialchars ашигласан эсэх
 * - read_count атомик update болсон эсэх
 * - filter_var redirect-д ашигласан эсэх
 * - loop reference unset хийсэн эсэх
 */
class CodeQualityTest extends TestCase
{
    private static string $appDir;

    public static function setUpBeforeClass(): void
    {
        self::$appDir = dirname(__DIR__, 3) . '/application';
    }

    // =============================================
    // \Error → \Exception солигдсон эсэх
    // =============================================

    /**
     * @dataProvider webControllerFilesProvider
     */
    public function testNoThrowErrorInWebControllers(string $file): void
    {
        $source = file_get_contents(self::$appDir . '/web/' . $file);

        $this->assertDoesNotMatchRegularExpression(
            '/throw\s+new\s+\\\\Error\s*\(/',
            $source,
            "$file should use \\Exception instead of \\Error for HTTP errors"
        );
    }

    public static function webControllerFilesProvider(): array
    {
        return [
            ['content/NewsController.php'],
            ['content/PageController.php'],
            ['shop/ShopController.php'],
        ];
    }

    // =============================================
    // XSS хамгаалалт - htmlspecialchars ашигласан эсэх
    // =============================================

    public function testWebExceptionHandlerUsesHtmlspecialchars(): void
    {
        $source = file_get_contents(self::$appDir . '/web/template/ExceptionHandler.php');

        $this->assertStringContainsString('htmlspecialchars', $source);
        $this->assertStringNotContainsString('"<h3>$message</h3>"', $source,
            'Raw $message interpolation in HTML should be removed');
    }

    public function testRaptorErrorHandlerUsesHtmlspecialchars(): void
    {
        $source = file_get_contents(self::$appDir . '/raptor/exception/ErrorHandler.php');

        $this->assertStringContainsString('htmlspecialchars', $source);
        $this->assertStringNotContainsString('"<h3', $source,
            'Raw $message interpolation in HTML should be removed');
    }

    // =============================================
    // read_count - атомик UPDATE болсон эсэх
    // =============================================

    /**
     * @dataProvider webControllerFilesProvider
     */
    public function testAtomicReadCountUpdate(string $file): void
    {
        $source = file_get_contents(self::$appDir . '/web/' . $file);

        // read_count=read_count+1 паттерн байх ёстой
        $this->assertMatchesRegularExpression(
            '/read_count\s*=\s*read_count\s*\+\s*1/',
            $source,
            "$file should use atomic SQL increment: read_count=read_count+1"
        );

        // Хуучин паттерн байх ёсгүй: PHP дээр +1 нэмээд бичих
        $this->assertDoesNotMatchRegularExpression(
            '/\$read_count\s*=.*\+\s*1/',
            $source,
            "$file should not calculate read_count in PHP (race condition)"
        );
    }

    // =============================================
    // Redirect - filter_var ашигласан эсэх
    // =============================================

    public function testHomeControllerRedirectSanitized(): void
    {
        $source = file_get_contents(self::$appDir . '/web/HomeController.php');

        $this->assertStringContainsString('filter_var', $source,
            'HomeController redirect should use filter_var for URL sanitization');
        $this->assertStringContainsString('FILTER_SANITIZE_URL', $source);
    }

    public function testRaptorControllerRedirectSanitized(): void
    {
        $source = file_get_contents(self::$appDir . '/raptor/Controller.php');

        $this->assertStringContainsString('filter_var', $source,
            'Raptor Controller redirectTo should use filter_var');
        $this->assertStringContainsString('FILTER_SANITIZE_URL', $source);
    }

    public function testLoginControllerRedirectsSanitized(): void
    {
        $source = file_get_contents(self::$appDir . '/raptor/authentication/LoginController.php');

        $this->assertStringContainsString('FILTER_SANITIZE_URL', $source,
            'LoginController redirects should use FILTER_SANITIZE_URL');

        // HTTP_REFERER sanitize хийгдсэн эсэх
        $this->assertStringContainsString('filter_var', $source);
    }

    // =============================================
    // SeoController - loop reference unset
    // =============================================

    public function testSeoControllerUnsetsLoopReference(): void
    {
        $source = file_get_contents(self::$appDir . '/web/seo/SeoController.php');

        // &$node reference ашигласан бол unset($node) байх ёстой
        if (str_contains($source, 'as &$node')) {
            $this->assertStringContainsString('unset($node)', $source,
                'Loop reference &$node should be unset after foreach');
        }
    }

    // =============================================
    // SeoController - ашиглагдаагүй $languages устгагдсан эсэх
    // =============================================

    public function testSeoControllerNoUnusedLanguagesInSitemapXml(): void
    {
        $source = file_get_contents(self::$appDir . '/web/seo/SeoController.php');

        // sitemapXml method дотор $languages = $this->getLanguages() байх ёсгүй
        $this->assertDoesNotMatchRegularExpression(
            '/function\s+sitemapXml.*?\$languages\s*=\s*\$this->getLanguages\(\)/s',
            $source,
            'Unused $languages variable should be removed from sitemapXml()'
        );
    }
}
