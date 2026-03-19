<?php

namespace Tests\Unit\Content;

use Tests\Support\RaptorTestCase;

/**
 * FilesController-ийн access control, validation логикийн unit тест.
 *
 * Source code шинжлэлд суурилсан тест:
 * - post() attachment хамгаалалт (non-files table + record_id=0)
 * - deactivate() non-files table хориглох
 * - Permission шалгалтууд
 * - Table name sanitization
 * - Default table сонголтын логик
 */
class FilesAccessControlTest extends RaptorTestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/file/FilesController.php'
        );
    }

    // =============================================
    // post() - attachment хамгаалалт
    // =============================================

    /**
     * post() нь files-ээс өөр хүснэгтэд record_id=0 байвал хориглох ёстой.
     * Энэ нь attachment-д шууд upload хийхээс хамгаална.
     */
    public function testPostRejectsNonFilesTableWithZeroRecordId(): void
    {
        \preg_match('/function\s+post\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'post() method not found');
        $body = $m[1];

        // table !== 'files' && record_id === 0 шалгалт байх ёстой
        $this->assertStringContainsString("table !== 'files'", $body,
            'post() must check if table is not files');
        $this->assertStringContainsString('record_id === 0', $body,
            'post() must check if record_id is 0 for non-files tables');
    }

    /**
     * post() нь files хүснэгтэд record_id=0 зөвшөөрдөг (ерөнхий файл upload).
     */
    public function testPostAllowsFilesTableWithZeroRecordId(): void
    {
        \preg_match('/function\s+post\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $body = $m[1];

        // Condition is: $table !== 'files' && $record_id === 0
        // This means files table with record_id=0 passes through (condition is false)
        $this->assertMatchesRegularExpression(
            '/\$table\s*!==\s*[\'"]files[\'"]\s*&&\s*\$record_id\s*===\s*0/',
            $body,
            'post() must use AND condition so files table with record_id=0 is allowed'
        );
    }

    /**
     * post() нь authentication шалгалт хийдэг.
     */
    public function testPostRequiresAuthentication(): void
    {
        \preg_match('/function\s+post\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString('isUserAuthorized()', $m[1],
            'post() must check authentication');
    }

    // =============================================
    // deactivate() - non-files table хориглох
    // =============================================

    /**
     * deactivate() нь files-ээс өөр хүснэгтийг хориглох.
     */
    public function testDeactivateRejectsNonFilesTable(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'deactivate() method not found');
        $body = $m[1];

        $this->assertStringContainsString("table !== 'files'", $body,
            'deactivate() must reject non-files table');
    }

    /**
     * deactivate() нь 403 error code шиддэг.
     */
    public function testDeactivateThrows403ForNonFilesTable(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $body = $m[1];

        $this->assertStringContainsString('403', $body,
            'deactivate() must throw 403 for non-files table');
    }

    /**
     * deactivate() нь system_content_delete эрх шалгадаг.
     */
    public function testDeactivateChecksDeletePermission(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("isUserCan('system_content_delete')", $m[1],
            'deactivate() must check system_content_delete permission');
    }

    /**
     * deactivate() эрхгүй хэрэглэгч зөвхөн өөрийн upload-ыг устгах боломжтой.
     */
    public function testDeactivateOwnerAccessChecksCreatedBy(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("record['created_by']", $m[1],
            'deactivate() must check created_by for owner access');
    }

    /**
     * deactivate() эрхгүй хэрэглэгч record-д холбогдсон файлыг устгах боломжгүй.
     */
    public function testDeactivateOwnerAccessChecksRecordId(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("record['record_id']", $m[1],
            'deactivate() must check record_id - only unattached files can be deleted by owner');
    }

    /**
     * deactivate() нь soft delete ашигладаг.
     */
    public function testDeactivateUsesSoftDelete(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString('deactivateById', $m[1],
            'deactivate() must use deactivateById for soft delete');
        $this->assertStringNotContainsString('DELETE FROM', $m[1],
            'deactivate() must NOT physically delete records');
    }

    // =============================================
    // index() - table name sanitization
    // =============================================

    /**
     * index() нь query parameter дахь table нэрийг sanitize хийдэг.
     */
    public function testIndexSanitizesTableName(): void
    {
        \preg_match('/function\s+index\s*\(\).*?\{(.+?)(?=\n    public\s)/s', self::$source, $m);
        $this->assertNotEmpty($m, 'index() method not found');
        $body = $m[1];

        $this->assertStringContainsString('preg_replace', $body,
            'index() must sanitize table name from query params');
        $this->assertStringContainsString('[^A-Za-z0-9_-]', $body,
            'index() must strip non-alphanumeric characters from table name');
    }

    /**
     * index() нь default table = 'files' байдаг.
     */
    public function testIndexDefaultsToFilesTable(): void
    {
        \preg_match('/function\s+index\s*\(\).*?\{(.+?)(?=\n    public\s)/s', self::$source, $m);
        $body = $m[1];

        $this->assertStringContainsString("table = 'files'", $body,
            'index() must default to files table when no query param');
    }

    /**
     * index() нь system_content_index эрх шалгадаг.
     */
    public function testIndexRequiresContentIndexPermission(): void
    {
        \preg_match('/function\s+index\s*\(\).*?\{(.+?)(?=\n    public\s)/s', self::$source, $m);
        $this->assertStringContainsString("isUserCan('system_content_index')", $m[1],
            'index() must check system_content_index permission');
    }

    // =============================================
    // list() - permission шалгалт
    // =============================================

    /**
     * list() нь system_content_index эрх шалгадаг.
     */
    public function testListRequiresContentIndexPermission(): void
    {
        \preg_match('/function\s+list\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'list() method not found');
        $this->assertStringContainsString("isUserCan('system_content_index')", $m[1],
            'list() must check system_content_index permission');
    }

    // =============================================
    // update() - permission + owner access
    // =============================================

    /**
     * update() нь system_content_update эрх шалгадаг.
     */
    public function testUpdateChecksUpdatePermission(): void
    {
        \preg_match('/function\s+update\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'update() method not found');
        $this->assertStringContainsString("isUserCan('system_content_update')", $m[1],
            'update() must check system_content_update permission');
    }

    /**
     * update() эрхгүй хэрэглэгч өөрийн upload хийсэн файлыг засах боломжтой.
     */
    public function testUpdateAllowsOwnerAccess(): void
    {
        \preg_match('/function\s+update\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("record['created_by']", $m[1],
            'update() must check created_by for owner access');
    }

    // =============================================
    // upload() - authentication
    // =============================================

    /**
     * upload() нь authentication шалгалт хийдэг.
     */
    public function testUploadRequiresAuthentication(): void
    {
        \preg_match('/function\s+upload\s*\(\).*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'upload() method not found');
        $this->assertStringContainsString('isUserAuthorized()', $m[1],
            'upload() must check authentication');
    }

    /**
     * upload() нь folder input-ыг sanitize хийдэг.
     */
    public function testUploadSanitizesFolderInput(): void
    {
        \preg_match('/function\s+upload\s*\(\).*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString('preg_replace', $m[1],
            'upload() must sanitize folder input');
    }

    // =============================================
    // Logging - бүх mutation үйлдэл лог бичдэг
    // =============================================

    public function testPostLogsAction(): void
    {
        \preg_match('/function\s+post\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("'action' => 'files-post'", $m[1],
            'post() must log with action context');
    }

    public function testDeactivateLogsAction(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("'action' => 'files-deactivate'", $m[1],
            'deactivate() must log with action context');
    }

    public function testUpdateLogsAction(): void
    {
        \preg_match('/function\s+update\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString("'action' => 'files-update'", $m[1],
            'update() must log with action context');
    }

    // =============================================
    // modal() - input validation
    // =============================================

    /**
     * modal() нь authentication шалгалт хийдэг.
     */
    public function testModalRequiresAuthentication(): void
    {
        \preg_match('/function\s+modal\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertNotEmpty($m, 'modal() method not found');
        $this->assertStringContainsString('isUserAuthorized()', $m[1],
            'modal() must check authentication');
    }

    /**
     * modal() нь id параметрыг numeric эсэхийг шалгадаг.
     */
    public function testModalValidatesNumericId(): void
    {
        \preg_match('/function\s+modal\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString('is_numeric', $m[1],
            'modal() must validate that id is numeric');
    }

    /**
     * modal() нь modal query parameter-ыг sanitize хийдэг.
     */
    public function testModalSanitizesModalParameter(): void
    {
        \preg_match('/function\s+modal\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$source, $m);
        $this->assertStringContainsString('preg_replace', $m[1],
            'modal() must sanitize modal parameter to prevent path traversal');
    }
}
