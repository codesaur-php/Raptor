<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Бүх хэлний ('*') бичлэгийн дүрмийг source level-д шалгах.
 *
 * News/Pages/Products-ийн code = '*' бичлэг вэб сайтын бүх хэл дээр харагдах
 * ёстой. Web талын хэлээр шүүдэг query бүр `code IN (:code, '*')` хэлбэртэй
 * байх ёстой - нэг газар `code=:code` хэвээр үлдвэл '*' бичлэг тэр
 * жагсаалтаас чимээгүй алга болно.
 */
class LanguageNeutralRecordsTest extends TestCase
{
    private static string $appDir;

    public static function setUpBeforeClass(): void
    {
        self::$appDir = dirname(__DIR__, 3) . '/application';
    }

    #[DataProvider('languageFilteredFilesProvider')]
    public function testWebQueriesIncludeAllLanguagesSentinel(string $file): void
    {
        $source = file_get_contents(self::$appDir . '/' . $file);

        $this->assertDoesNotMatchRegularExpression(
            '/\bcode\s*=\s*:code\b/',
            $source,
            "$file still filters with a bare code=:code - use code IN (:code, '*') so language-neutral records are included"
        );
        $this->assertStringContainsString(
            "IN (:code, '*')",
            $source,
            "$file should filter with code IN (:code, '*')"
        );
    }

    public static function languageFilteredFilesProvider(): array
    {
        return [
            ['web/content/NewsController.php'],
            ['web/content/PageController.php'],
            ['web/shop/ShopController.php'],
            ['web/service/SeoController.php'],
            ['web/service/SearchController.php'],
            ['dashboard/content/news/NewsModel.php'],
            ['dashboard/content/page/PagesModel.php'],
        ];
    }

    public function testWebLayoutSkipsAllLanguagesCodeForHtmlLang(): void
    {
        $source = file_get_contents(self::$appDir . '/web/template/TemplateController.php');

        $this->assertStringContainsString(
            "\$vars[\$key] !== '*'",
            $source,
            "webTemplate() must not map code='*' to record_code - <html lang=\"*\"> is invalid"
        );
    }

    public function testPagesParentCodeRule(): void
    {
        $source = file_get_contents(self::$appDir . '/dashboard/content/page/PagesController.php');

        $this->assertStringContainsString('function isParentCodeCompatible', $source);
        $this->assertStringContainsString(
            "return \$parentCode === '*' || \$parentCode === \$childCode;",
            $source,
            'A parent page must share the child language or be language-neutral'
        );
        $this->assertStringNotContainsString(
            "\$parentRow['code'] !== \$payload['code']",
            $source,
            'Insert must use isParentCodeCompatible() instead of strict code equality'
        );
        $this->assertStringNotContainsString(
            "\$parentRow['code'] !== \$record['code']",
            $source,
            'Update must use isParentCodeCompatible() instead of strict code equality'
        );
        $this->assertStringContainsString(
            "\$this->text('change-child-pages-language-first')",
            $source,
            'Update must reject a language change that would orphan direct child pages'
        );
    }
}
