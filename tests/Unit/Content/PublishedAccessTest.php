<?php

namespace Tests\Unit\Content;

use Tests\Support\RaptorTestCase;

/**
 * News болон Pages controller-ийн published access logic тест.
 *
 * CLAUDE.md: "Controllers with published field allow users without
 * _update/_delete permission to edit/delete their own unpublished records."
 *
 * Энэ тест нь update() болон deactivate() дахь owner access логикийг
 * source code шинжлэлээр шалгана.
 */
class PublishedAccessTest extends RaptorTestCase
{
    private static string $newsSource;
    private static string $pagesSource;

    public static function setUpBeforeClass(): void
    {
        self::$newsSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/NewsController.php'
        );
        self::$pagesSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/page/PagesController.php'
        );
    }

    // =============================================
    // NewsController::update() - owner access
    // =============================================

    /**
     * NewsController::update() нь system_content_update эрх шалгадаг.
     */
    public function testNewsUpdateChecksUpdatePermission(): void
    {
        $this->assertMethodChecksPermission(
            self::$newsSource, 'update', 'system_content_update',
            'NewsController::update()'
        );
    }

    /**
     * NewsController::update() эрхгүй хэрэглэгч өөрийн unpublished бичлэгийг засах боломжтой.
     * created_by === userId AND published === 0 нөхцөл шалгадаг.
     */
    public function testNewsUpdateAllowsOwnerUnpublished(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'update');
        $this->assertNotEmpty($body, 'update() not found in NewsController');

        // created_by шалгалт
        $this->assertStringContainsString("record['created_by']", $body,
            'NewsController::update() must check created_by for owner access');

        // published === 0 шалгалт
        $this->assertStringContainsString("record['published']", $body,
            'NewsController::update() must check published status for owner access');
    }

    /**
     * NewsController::update() нь published бичлэгт system_content_publish эрх шалгадаг.
     */
    public function testNewsUpdateRequiresPublishPermissionForPublishedRecord(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'update');
        $this->assertStringContainsString("isUserCan('system_content_publish')", $body,
            'NewsController::update() must check system_content_publish for published records');
    }

    // =============================================
    // NewsController::deactivate() - owner access
    // =============================================

    /**
     * NewsController::deactivate() нь system_content_delete эрх шалгадаг.
     */
    public function testNewsDeactivateChecksDeletePermission(): void
    {
        $this->assertMethodChecksPermission(
            self::$newsSource, 'deactivate', 'system_content_delete',
            'NewsController::deactivate()'
        );
    }

    /**
     * NewsController::deactivate() эрхгүй хэрэглэгч өөрийн unpublished бичлэгийг устгах боломжтой.
     */
    public function testNewsDeactivateAllowsOwnerUnpublished(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'deactivate');
        $this->assertNotEmpty($body, 'deactivate() not found in NewsController');

        $this->assertStringContainsString("record['created_by']", $body,
            'NewsController::deactivate() must check created_by');
        $this->assertStringContainsString("record['published']", $body,
            'NewsController::deactivate() must check published status');
    }

    /**
     * NewsController::deactivate() published бичлэгийг эрхгүй хэрэглэгч устгах боломжгүй.
     * published !== 0 байвал permission error шидэх ёстой.
     */
    public function testNewsDeactivateBlocksOwnerOnPublished(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'deactivate');

        // The condition is: created_by !== userId || published !== 0
        // This means if published !== 0, the check fails even if user owns it
        $this->assertMatchesRegularExpression(
            '/\(int\)\$record\[.published.\]\s*!==\s*0/',
            $body,
            'NewsController::deactivate() must reject owner access on published records'
        );
    }

    // =============================================
    // PagesController::update() - owner access
    // =============================================

    /**
     * PagesController::update() нь system_content_update эрх шалгадаг.
     */
    public function testPagesUpdateChecksUpdatePermission(): void
    {
        $this->assertMethodChecksPermission(
            self::$pagesSource, 'update', 'system_content_update',
            'PagesController::update()'
        );
    }

    /**
     * PagesController::update() эрхгүй хэрэглэгч өөрийн unpublished бичлэгийг засах боломжтой.
     */
    public function testPagesUpdateAllowsOwnerUnpublished(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'update');
        $this->assertNotEmpty($body, 'update() not found in PagesController');

        $this->assertStringContainsString("record['created_by']", $body,
            'PagesController::update() must check created_by for owner access');
        $this->assertStringContainsString("record['published']", $body,
            'PagesController::update() must check published status for owner access');
    }

    /**
     * PagesController::update() нь published бичлэгт publish эрх шалгадаг.
     */
    public function testPagesUpdateRequiresPublishPermissionForPublishedRecord(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'update');
        $this->assertStringContainsString("isUserCan('system_content_publish')", $body,
            'PagesController::update() must check system_content_publish for published records');
    }

    // =============================================
    // PagesController::deactivate() - owner access
    // =============================================

    /**
     * PagesController::deactivate() нь system_content_delete эрх шалгадаг.
     */
    public function testPagesDeactivateChecksDeletePermission(): void
    {
        $this->assertMethodChecksPermission(
            self::$pagesSource, 'deactivate', 'system_content_delete',
            'PagesController::deactivate()'
        );
    }

    /**
     * PagesController::deactivate() эрхгүй хэрэглэгч өөрийн unpublished бичлэгийг устгах боломжтой.
     */
    public function testPagesDeactivateAllowsOwnerUnpublished(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'deactivate');
        $this->assertNotEmpty($body, 'deactivate() not found in PagesController');

        $this->assertStringContainsString("record['created_by']", $body,
            'PagesController::deactivate() must check created_by');
        $this->assertStringContainsString("record['published']", $body,
            'PagesController::deactivate() must check published status');
    }

    /**
     * PagesController::deactivate() published бичлэгийг эрхгүй хэрэглэгч устгах боломжгүй.
     */
    public function testPagesDeactivateBlocksOwnerOnPublished(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'deactivate');

        $this->assertMatchesRegularExpression(
            '/\(int\)\$record\[.published.\]\s*!==\s*0/',
            $body,
            'PagesController::deactivate() must reject owner access on published records'
        );
    }

    // =============================================
    // Soft delete - бүх deactivate нь is_active=0
    // =============================================

    /**
     * NewsController::deactivate() нь deactivateById ашигладаг (soft delete).
     */
    public function testNewsDeactivateUsesSoftDelete(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'deactivate');
        $this->assertStringContainsString('deactivateById', $body,
            'NewsController::deactivate() must use deactivateById (soft delete)');
        $this->assertStringNotContainsString('DELETE FROM', $body,
            'NewsController::deactivate() must NOT physically delete records');
    }

    /**
     * PagesController::deactivate() нь deactivateById ашигладаг (soft delete).
     */
    public function testPagesDeactivateUsesSoftDelete(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'deactivate');
        $this->assertStringContainsString('deactivateById', $body,
            'PagesController::deactivate() must use deactivateById (soft delete)');
        $this->assertStringNotContainsString('DELETE FROM', $body,
            'PagesController::deactivate() must NOT physically delete records');
    }

    // =============================================
    // Consistent pattern - News vs Pages
    // =============================================

    /**
     * News болон Pages controller хоёулаа ижил owner access pattern ашигладаг.
     */
    public function testConsistentOwnerAccessPattern(): void
    {
        $newsUpdate = $this->extractMethodBody(self::$newsSource, 'update');
        $pagesUpdate = $this->extractMethodBody(self::$pagesSource, 'update');

        // Both should check: !isUserCan('system_content_update')
        // then check created_by and published
        $newsHasPattern = \str_contains($newsUpdate, "isUserCan('system_content_update')")
            && \str_contains($newsUpdate, "record['created_by']")
            && \str_contains($newsUpdate, "record['published']");
        $pagesHasPattern = \str_contains($pagesUpdate, "isUserCan('system_content_update')")
            && \str_contains($pagesUpdate, "record['created_by']")
            && \str_contains($pagesUpdate, "record['published']");

        $this->assertTrue($newsHasPattern, 'NewsController::update() missing owner access pattern');
        $this->assertTrue($pagesHasPattern, 'PagesController::update() missing owner access pattern');
    }

    // =============================================
    // view() - published бичлэгийг бүх admin харах боломжтой
    // =============================================

    /**
     * NewsController::view() нийтлэгдсэн бичлэгийг system_content_index эрхгүй ч харах боломжтой.
     */
    public function testNewsViewAllowsPublishedForAllAdmins(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'view');
        $this->assertNotEmpty($body, 'view() not found in NewsController');

        // Pattern: !isUserCan('system_content_index') && published !== 1
        $this->assertStringContainsString("isUserCan('system_content_index')", $body,
            'NewsController::view() must check permission');
        $this->assertStringContainsString("record['published']", $body,
            'NewsController::view() must check published status');
    }

    /**
     * PagesController::view() нийтлэгдсэн бичлэгийг system_content_index эрхгүй ч харах боломжтой.
     */
    public function testPagesViewAllowsPublishedForAllAdmins(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'view');
        $this->assertNotEmpty($body, 'view() not found in PagesController');

        $this->assertStringContainsString("isUserCan('system_content_index')", $body,
            'PagesController::view() must check permission');
        $this->assertStringContainsString("record['published']", $body,
            'PagesController::view() must check published status');
    }

    // =============================================
    // insert() - publish эрх шалгалт
    // =============================================

    /**
     * NewsController::insert() нь published=1 үед system_content_publish эрх шалгадаг.
     */
    public function testNewsInsertChecksPublishPermission(): void
    {
        $body = $this->extractMethodBody(self::$newsSource, 'insert');
        $this->assertStringContainsString("isUserCan('system_content_publish')", $body,
            'NewsController::insert() must check system_content_publish');
    }

    /**
     * PagesController::insert() нь published=1 үед system_content_publish эрх шалгадаг.
     */
    public function testPagesInsertChecksPublishPermission(): void
    {
        $body = $this->extractMethodBody(self::$pagesSource, 'insert');
        $this->assertStringContainsString("isUserCan('system_content_publish')", $body,
            'PagesController::insert() must check system_content_publish');
    }

    // =============================================
    // Helper methods
    // =============================================

    private function extractMethodBody(string $source, string $method): string
    {
        \preg_match('/function\s+' . $method . '\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n\})/s', $source, $m);
        return $m[1] ?? '';
    }

    private function assertMethodChecksPermission(string $source, string $method, string $permission, string $context): void
    {
        $body = $this->extractMethodBody($source, $method);
        $this->assertNotEmpty($body, "$method() not found");
        $this->assertStringContainsString(
            "isUserCan('$permission')",
            $body,
            "$context must check $permission permission"
        );
    }
}
