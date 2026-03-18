<?php

namespace Tests\Unit\Content;

use Tests\Support\RaptorTestCase;

/**
 * News comments системийн unit тест.
 *
 * Dashboard CommentsController болон Web NewsController::commentSubmit
 * дахь эрхийн шалгалт, валидаци, бизнес логикийг шалгана.
 */
class CommentsTest extends RaptorTestCase
{
    private static string $dashboardController;
    private static string $webController;

    public static function setUpBeforeClass(): void
    {
        self::$dashboardController = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/CommentsController.php'
        );
        self::$webController = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/web/content/NewsController.php'
        );
    }

    // =============================================
    // Dashboard CommentsController - эрхийн шалгалт
    // =============================================

    /**
     * index() нь system_content_index эрх шалгадаг эсэх.
     */
    public function testIndexRequiresContentIndexPermission(): void
    {
        $this->assertStringContainsString(
            "isUserCan('system_content_index')",
            self::$dashboardController,
            'index() must check system_content_index permission'
        );
    }

    /**
     * list() нь system_content_index эрх шалгадаг эсэх.
     */
    public function testListRequiresContentIndexPermission(): void
    {
        // list method доторх permission check
        \preg_match('/function\s+list\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertNotEmpty($m, 'list() method not found');
        $this->assertStringContainsString("isUserCan('system_content_index')", $m[1],
            'list() must check system_content_index permission');
    }

    /**
     * comment() нь system_content_index эрх шалгадаг эсэх.
     */
    public function testCommentRequiresContentIndexPermission(): void
    {
        \preg_match('/function\s+comment\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertNotEmpty($m, 'comment() method not found');
        $this->assertStringContainsString("isUserCan('system_content_index')", $m[1],
            'comment() must check system_content_index permission');
    }

    /**
     * reply() нь system_content_update эрх шалгадаг эсэх.
     */
    public function testReplyRequiresContentUpdatePermission(): void
    {
        \preg_match('/function\s+reply\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertNotEmpty($m, 'reply() method not found');
        $this->assertStringContainsString("isUserCan('system_content_update')", $m[1],
            'reply() must check system_content_update permission');
    }

    /**
     * deactivate() нь system_content_delete эрх шалгадаг эсэх.
     */
    public function testDeactivateRequiresContentDeletePermission(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertNotEmpty($m, 'deactivate() method not found');
        $this->assertStringContainsString("isUserCan('system_content_delete')", $m[1],
            'deactivate() must check system_content_delete permission');
    }

    // =============================================
    // Dashboard CommentsController - бизнес логик
    // =============================================

    /**
     * comment() хоосон comment текст зөвшөөрөхгүй.
     */
    public function testCommentRejectsEmptyText(): void
    {
        \preg_match('/function\s+comment\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertMatchesRegularExpression(
            '/empty\s*\(\s*\$comment\s*\)/',
            $m[1],
            'comment() must validate that comment text is not empty'
        );
    }

    /**
     * reply() 1-level reply хязгаарлалт - parent_id байгаа comment-д reply хийхийг хориглох.
     */
    public function testReplyEnforcesOneLevelLimit(): void
    {
        \preg_match('/function\s+reply\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertNotEmpty($m, 'reply() method not found');
        $this->assertStringContainsString("parent['parent_id']", $m[1],
            'reply() must check parent_id to enforce 1-level reply limit');
    }

    /**
     * deactivate() нь reply-уудыг мөн идэвхгүй болгодог эсэх (cascade soft delete).
     */
    public function testDeactivateCascadesToReplies(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertMatchesRegularExpression(
            '/is_active\s*=\s*0\s+WHERE\s+parent_id/',
            $m[1],
            'deactivate() must also deactivate child replies'
        );
    }

    /**
     * deactivate() нь soft delete хийдэг (is_active=0), бичлэг устгахгүй.
     */
    public function testDeactivateUsesSoftDelete(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertStringContainsString('is_active=0', $m[1],
            'deactivate() must use soft delete (is_active=0)');
        $this->assertStringNotContainsString('DELETE FROM', $m[1],
            'deactivate() must NOT physically delete records');
    }

    /**
     * comment() нь мэдээний comment тохиргоог шалгадаг эсэх.
     */
    public function testCommentChecksNewsCommentEnabled(): void
    {
        \preg_match('/function\s+comment\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertStringContainsString("news['comment']", $m[1],
            'comment() must verify that comments are enabled on the news item');
    }

    // =============================================
    // Dashboard CommentsController - logging
    // =============================================

    /**
     * comment() лог бичдэг эсэх (badge system-д шаардлагатай).
     */
    public function testCommentLogsAction(): void
    {
        \preg_match('/function\s+comment\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertStringContainsString("'action' => 'comment-insert'", $m[1],
            'comment() must log with action context for badge system');
    }

    /**
     * reply() лог бичдэг эсэх.
     */
    public function testReplyLogsAction(): void
    {
        \preg_match('/function\s+reply\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertStringContainsString("'action' => 'comment-reply'", $m[1],
            'reply() must log with action context for badge system');
    }

    /**
     * deactivate() лог бичдэг эсэх.
     */
    public function testDeactivateLogsAction(): void
    {
        \preg_match('/function\s+deactivate\s*\(.*?\{(.+?)(?=\n    public\s|\n\})/s', self::$dashboardController, $m);
        $this->assertStringContainsString("'action' => 'comment-deactivate'", $m[1],
            'deactivate() must log with action context for badge system');
    }

    // =============================================
    // Web NewsController::commentSubmit - валидаци
    // =============================================

    /**
     * commentSubmit() нь spam protection ашигладаг эсэх.
     */
    public function testWebCommentUsesSpamProtection(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertNotEmpty($m, 'commentSubmit() method not found');
        $this->assertStringContainsString('validateSpamProtection', $m[1],
            'commentSubmit() must use spam protection');
    }

    /**
     * commentSubmit() нь name шалгадаг эсэх.
     */
    public function testWebCommentValidatesName(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertMatchesRegularExpression(
            '/empty\s*\(\s*\$name\s*\)/',
            $m[1],
            'commentSubmit() must validate that name is not empty'
        );
    }

    /**
     * commentSubmit() нь comment текст шалгадаг эсэх.
     */
    public function testWebCommentValidatesCommentText(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertMatchesRegularExpression(
            '/empty\s*\(\s*\$comment\s*\)/',
            $m[1],
            'commentSubmit() must validate that comment text is not empty'
        );
    }

    /**
     * commentSubmit() нь email формат шалгадаг эсэх.
     */
    public function testWebCommentValidatesEmail(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString('FILTER_VALIDATE_EMAIL', $m[1],
            'commentSubmit() must validate email format');
    }

    /**
     * commentSubmit() нь link spam шалгадаг эсэх.
     */
    public function testWebCommentChecksLinkSpam(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString('checkLinkSpam', $m[1],
            'commentSubmit() must check for link spam');
    }

    /**
     * commentSubmit() 1-level reply хязгаарлалт.
     */
    public function testWebCommentEnforcesOneLevelReply(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString("parentComment['parent_id']", $m[1],
            'commentSubmit() must enforce 1-level reply limit');
    }

    /**
     * commentSubmit() нь мэдээний comment тохиргоог шалгадаг эсэх.
     */
    public function testWebCommentChecksNewsCommentEnabled(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString("news['comment']", $m[1],
            'commentSubmit() must check that comments are enabled on the news item');
    }

    // =============================================
    // Web commentSubmit - badge system нийцэл
    // =============================================

    /**
     * Web comment log-д auth_user.id байхгүй байх ёстой (badge бүх админд харагдахын тулд).
     */
    public function testWebCommentLogOmitsAuthUserId(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertNotEmpty($m, 'commentSubmit() method not found');

        // auth_user array-г олж авч, доторх key-уудад 'id' байхгүй эсэхийг шалгах
        $this->assertStringContainsString("'auth_user'", $m[1],
            'commentSubmit() must include auth_user in log context');

        // auth_user array блокийг regex-ээр ялгаж авах
        \preg_match("/'auth_user'\s*=>\s*\[(.+?)\]/s", $m[1], $authUser);
        $this->assertNotEmpty($authUser, 'auth_user array not found in log context');
        $this->assertDoesNotMatchRegularExpression(
            "/'id'\s*=>/",
            $authUser[1],
            'Web comment auth_user must NOT include id field (so badges count for all admins)'
        );
    }

    /**
     * commentSubmit() лог бичдэг эсэх.
     */
    public function testWebCommentLogsAction(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString("'action' => 'comment-insert'", $m[1],
            'commentSubmit() must log with action context for badge system');
    }

    // =============================================
    // Web commentSubmit - rate limiting
    // =============================================

    /**
     * commentSubmit() нь session-д rate limit тавьдаг эсэх.
     */
    public function testWebCommentSetsRateLimitSession(): void
    {
        \preg_match('/function\s+commentSubmit\s*\(.*?\{(.+?)(?=\n    public\s|\n    private\s|\n    protected\s|\n\})/s', self::$webController, $m);
        $this->assertStringContainsString('_last_comment_at', $m[1],
            'commentSubmit() must set session timestamp for rate limiting');
    }

    // =============================================
    // CommentsModel - бүтцийн шалгалт
    // =============================================

    /**
     * CommentsModel нь зөв table name ашигладаг.
     */
    public function testCommentsModelTableName(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/CommentsModel.php'
        );
        $this->assertStringContainsString("setTable('news_comments')", $source,
            'CommentsModel must use news_comments table');
    }

    /**
     * CommentsModel бүх шаардлагатай column-уудтай.
     */
    public function testCommentsModelHasRequiredColumns(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/CommentsModel.php'
        );
        $required = ['id', 'news_id', 'parent_id', 'created_by', 'name', 'email', 'comment', 'is_active', 'created_at'];
        foreach ($required as $col) {
            $this->assertStringContainsString("'$col'", $source,
                "CommentsModel must have '$col' column");
        }
    }

    /**
     * CommentsModel-д FK constraint байх ёстой.
     */
    public function testCommentsModelHasForeignKeys(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/CommentsModel.php'
        );
        $this->assertStringContainsString('fk_news_id', $source, 'Must have FK to news table');
        $this->assertStringContainsString('fk_parent_id', $source, 'Must have self-referencing FK for replies');
        $this->assertStringContainsString('fk_created_by', $source, 'Must have FK to users table');
    }

    /**
     * CommentsModel-д index байх ёстой.
     */
    public function testCommentsModelHasIndexes(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/content/news/CommentsModel.php'
        );
        $this->assertStringContainsString('idx_news_active', $source,
            'Must have composite index on (news_id, is_active)');
        $this->assertStringContainsString('idx_created', $source,
            'Must have index on created_at');
    }

    // =============================================
    // Routing - шалгалт
    // =============================================

    /**
     * Web comment route нь /session/ prefix ашигладаг (SessionMiddleware-д бичих эрхтэй).
     */
    public function testWebCommentRouteUsesSessionPrefix(): void
    {
        $router = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/web/WebRouter.php'
        );
        $this->assertMatchesRegularExpression(
            '#/session/news/.*comment#',
            $router,
            'Web comment route must use /session/ prefix for SessionMiddleware write access'
        );
    }
}
