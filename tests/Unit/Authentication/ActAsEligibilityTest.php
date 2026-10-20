<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;

use Dashboard\Authentication\JWTAuthMiddleware;

/**
 * Act As (role preview)-ийн эрхийн дүрмийн тест.
 *
 * Act As нь эрхийг зөвхөн багасгах ёстой:
 *  - system_coder         -> coder-оос бусад дурын роль
 *  - system_rbac эрхтэй   -> бүх эрх нь өөрт байгаа роль (subset)
 *  - бусад, 2+ ролтой     -> зөвхөн өөрийн ролиуд
 *  - ганц ролтой          -> Act As цэс байхгүй
 *  - system_coder ролиор хэзээ ч болохгүй
 */
class ActAsEligibilityTest extends RaptorTestCase
{
    private const FACILITY_ADMIN = [
        'common_facility_admin' => ['common_facility_index' => true, 'common_facility_update' => true],
        'common_inspector'      => ['common_facility_index' => true]
    ];

    // ---------------------------------------------------------
    // canUseActAs()
    // ---------------------------------------------------------

    public function testCoderCanUseActAs(): void
    {
        $this->assertTrue(JWTAuthMiddleware::canUseActAs(['system_coder' => []]));
    }

    public function testRbacManagerWithSingleRoleCanUseActAs(): void
    {
        $this->assertTrue(JWTAuthMiddleware::canUseActAs(['system_admin' => ['system_rbac' => true]]));
    }

    public function testMultiRoleUserCanUseActAs(): void
    {
        $this->assertTrue(JWTAuthMiddleware::canUseActAs(self::FACILITY_ADMIN));
    }

    public function testSingleRoleUserCannotUseActAs(): void
    {
        $this->assertFalse(JWTAuthMiddleware::canUseActAs(['system_editor' => ['system_content_index' => true]]));
        $this->assertFalse(JWTAuthMiddleware::canUseActAs([]));
    }

    // ---------------------------------------------------------
    // canActAs()
    // ---------------------------------------------------------

    public function testNobodyCanActAsCoder(): void
    {
        $this->assertFalse(JWTAuthMiddleware::canActAs(['system_coder' => []], 'system_coder', []));
        $this->assertFalse(JWTAuthMiddleware::canActAs(['system_admin' => ['system_rbac' => true]], 'system_coder', []));
    }

    public function testCoderCanActAsAnyOtherRole(): void
    {
        $this->assertTrue(JWTAuthMiddleware::canActAs(['system_coder' => []], 'system_admin', ['system_rbac' => true]));
    }

    public function testMultiRoleUserCanActAsOwnRole(): void
    {
        $this->assertTrue(JWTAuthMiddleware::canActAs(
            self::FACILITY_ADMIN, 'common_inspector', ['common_facility_index' => true]
        ));
    }

    /**
     * system_rbac-гүй хэрэглэгч өөрийн биш ролиор - эрх нь subset байсан ч - болохгүй.
     */
    public function testMultiRoleUserCannotActAsForeignRole(): void
    {
        $this->assertFalse(JWTAuthMiddleware::canActAs(
            self::FACILITY_ADMIN, 'common_viewer', ['common_facility_index' => true]
        ));
    }

    public function testRbacManagerCanActAsSubsetRole(): void
    {
        $real = ['system_admin' => ['system_rbac' => true, 'system_content_index' => true]];

        $this->assertTrue(JWTAuthMiddleware::canActAs($real, 'system_viewer', ['system_content_index' => true]));
    }

    public function testRbacManagerCannotActAsRoleWithForeignPermission(): void
    {
        $real = ['system_admin' => ['system_rbac' => true, 'system_content_index' => true]];

        $this->assertFalse(JWTAuthMiddleware::canActAs(
            $real, 'system_manager', ['system_content_index' => true, 'system_development' => true]
        ));
    }

    /**
     * Subset шалгалт нь бүх ролийн нийлбэр эрхээр явна.
     */
    public function testSubsetUsesUnionOfAllRealRoles(): void
    {
        $real = [
            'system_admin'  => ['system_rbac' => true],
            'system_editor' => ['system_content_update' => true]
        ];

        $this->assertTrue(JWTAuthMiddleware::canActAs(
            $real, 'system_writer', ['system_rbac' => true, 'system_content_update' => true]
        ));
    }

    // ---------------------------------------------------------
    // Хүсэлт бүрд жинхэнэ эрхээр дахин шалгах
    // ---------------------------------------------------------

    public function testMiddlewareRevalidatesAgainstRealPermissions(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/authentication/JWTAuthMiddleware.php'
        );
        $this->assertStringContainsString(
            'self::canActAs($realPermissions, $actAs[\'role\'], $actAs[\'permissions\'])',
            $source,
            'JWTAuthMiddleware must re-check Act As eligibility against the real permissions on every request'
        );
        $this->assertStringNotContainsString(
            "if (isset(\$permissions['system_coder'])\n                && !empty(\$_SESSION[self::ACT_AS_SESSION_KEY])",
            $source,
            'Act As must no longer be coder-only'
        );
    }

    public function testControllerUsesSameRule(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/rbac/ActAsController.php'
        );
        $this->assertSame(2, \substr_count($source, 'JWTAuthMiddleware::canUseActAs($real)'),
            'roles() and start() must both check canUseActAs()');
        $this->assertSame(2, \substr_count($source, 'JWTAuthMiddleware::canActAs($real'),
            'roles() must filter and start() must check the chosen role with canActAs()');
    }
}
