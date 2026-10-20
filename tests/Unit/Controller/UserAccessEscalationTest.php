<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;

use Dashboard\Authentication\User;
use Dashboard\User\UsersController;

/**
 * Хэрэглэгчийн дүр, байгууллагыг өөрийн эрхийн хүрээнээс хэтрүүлэн
 * өөрчлөхөөс хамгаалах дүрмийн тест (UsersController).
 *
 * Дүрэм (coder биш хэрэглэгч):
 *  - system_coder хэрэглэгчийн дүр, байгууллагад хүрэхгүй
 *  - өөрт байхгүй эрхтэй дүрийг оноох, хасахгүй (coder дүр хэзээ ч)
 *  - RBAC матрицад зөвхөн өөрт байгаа эрхийг дүрд нэмж, хасна
 *  - зөвхөн өөрийн харьяалагддаг байгууллагад нэмж, хасна
 *  - өөрийн хандалтыг өөрчилж болно, гэхдээ өөрийгөө түгжихгүй (удирдлагын эрх, одоогийн байгууллага)
 *
 * Хоёр төрлийн шалгалт: private helper-үүдийн зан төлөв (reflection) ба
 * бичих зам бүр шалгалтыг configure*() / insert()-ээс ӨМНӨ дууддаг эсэх (source).
 */
class UserAccessEscalationTest extends RaptorTestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/user/UsersController.php'
        );
    }

    private function controller(User $user): UsersController
    {
        $request = $this->createMockRequest([
            'pdo'  => $this->createMock(\PDO::class),
            'user' => $user
        ]);
        return new UsersController($request);
    }

    private function call(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        return $ref->invokeArgs($object, $args);
    }

    /**
     * Method-ийн body-г source-оос салгаж авах.
     */
    private function methodBody(string $name): string
    {
        \preg_match(
            '/function\s+' . $name . '\s*\(.*?\{(.+?)(?=\n    (?:public|private|protected)\s|\n\})/s',
            self::$source,
            $m
        );
        $this->assertNotEmpty($m, "$name() method not found");
        return $m[1];
    }

    private function assertCalledBefore(string $body, string $guard, string $write, string $method): void
    {
        $g = \strpos($body, $guard);
        $w = \strpos($body, $write);
        $this->assertNotFalse($g, "$method() must call $guard");
        $this->assertNotFalse($w, "$method() must contain $write");
        $this->assertLessThan($w, $g, "$method() must call $guard before $write");
    }

    // ---------------------------------------------------------
    // isWithinOwnPermissions()
    // ---------------------------------------------------------

    public function testRoleWithinOwnPermissionsIsAllowed(): void
    {
        $user = $this->createUser(['system_manager' => ['system_content_index' => true, 'system_content_update' => true]], ['id' => 5]);
        $role = ['key' => 'system_editor', 'is_coder' => false, 'permissions' => ['system_content_index']];

        $this->assertTrue($this->call($this->controller($user), 'isWithinOwnPermissions', [$role]));
    }

    public function testRoleWithForeignPermissionIsRejected(): void
    {
        $user = $this->createUser(['system_manager' => ['system_content_index' => true]], ['id' => 5]);
        $role = ['key' => 'system_admin', 'is_coder' => false, 'permissions' => ['system_content_index', 'system_rbac']];

        $this->assertFalse($this->call($this->controller($user), 'isWithinOwnPermissions', [$role]));
    }

    public function testCoderRoleIsNeverWithinNonCoderPermissions(): void
    {
        $user = $this->createUser(['system_admin' => ['system_rbac' => true]], ['id' => 5]);
        $role = ['key' => 'system_coder', 'is_coder' => true, 'permissions' => []];

        $this->assertFalse($this->call($this->controller($user), 'isWithinOwnPermissions', [$role]));
    }

    public function testCoderPassesEveryRole(): void
    {
        $role = ['key' => 'system_admin', 'is_coder' => false, 'permissions' => ['system_rbac']];

        $this->assertTrue($this->call($this->controller($this->createCoder()), 'isWithinOwnPermissions', [$role]));
    }

    // ---------------------------------------------------------
    // assertCanManageAccessOf()
    // ---------------------------------------------------------

    /**
     * Өөрийн хандалтыг өөрчлөхийг хориглохгүй - эрх хэтрэлтийг subset / харьяаллын
     * дүрэм, өөрийгөө түгжихийг assert*ChangesAllowed() хаадаг.
     */
    public function testNonCoderMayManageOwnAccess(): void
    {
        $body = $this->methodBody('assertCanManageAccessOf');
        $this->assertStringNotContainsString('getUserId()', $body,
            'assertCanManageAccessOf() must not block changes to one\'s own access');
    }

    // ---------------------------------------------------------
    // Өөрийгөө түгжихээс хамгаалах
    // ---------------------------------------------------------

    public function testManagementPermissionsList(): void
    {
        $this->assertSame(
            ['system_rbac', 'system_user_organization_set'],
            \Dashboard\RBAC\Permissions::MANAGEMENT
        );
    }

    public function testOwnRoleChangeKeepsManagementPermissions(): void
    {
        $body = $this->methodBody('assertRoleChangesAllowed');
        $this->assertStringContainsString('$id == $this->getUserId()', $body);
        $this->assertStringContainsString('Permissions::MANAGEMENT', $body);
        $this->assertStringContainsString("'cannot-remove-own-management-permission'", $body);
    }

    public function testOwnOrganizationChangeKeepsCurrentOrganization(): void
    {
        $body = $this->methodBody('assertOrganizationChangesAllowed');
        $this->assertStringContainsString('$id == $this->getUserId()', $body);
        $this->assertStringContainsString("'cannot-remove-own-current-organization'", $body);
    }

    // ---------------------------------------------------------
    // RBAC role matrix (RBACController::setRolePermission)
    // ---------------------------------------------------------

    public function testRoleMatrixGuardsBeforeWrite(): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/application/dashboard/rbac/RBACController.php');
        \preg_match('/function\s+setRolePermission\s*\(.*?\{(.+?)(?=\n    (?:public|private|protected)\s|\n\})/s', $source, $m);
        $this->assertNotEmpty($m, 'setRolePermission() method not found');
        $this->assertCalledBefore($m[1], '$this->assertRolePermissionChangeAllowed(', '$model->insert(', 'setRolePermission');
        $this->assertCalledBefore($m[1], '$this->assertRolePermissionChangeAllowed(', '$model->deleteById(', 'setRolePermission');

        \preg_match('/function\s+assertRolePermissionChangeAllowed\s*\(.*?\{(.+?)(?=\n    (?:public|private|protected)\s|\n\})/s', $source, $g);
        $this->assertNotEmpty($g, 'assertRolePermissionChangeAllowed() method not found');
        $this->assertStringContainsString('$this->isUserCan($key)', $g[1]);
        $this->assertStringContainsString("'permission-not-own'", $g[1]);
        $this->assertStringContainsString('Permissions::MANAGEMENT', $g[1]);
    }

    public function testCoderCanManageOwnAccess(): void
    {
        $this->call($this->controller($this->createCoder()), 'assertCanManageAccessOf', [1]);
        $this->addToAssertionCount(1);
    }

    public function testAssertCanManageAccessOfChecksCoderTarget(): void
    {
        $body = $this->methodBody('assertCanManageAccessOf');
        $this->assertStringContainsString("r.alias='system' AND r.name='coder'", $body);
        $this->assertStringContainsString("'cannot-change-coder-access'", $body);
    }

    // ---------------------------------------------------------
    // Бичих зам бүр шалгалтаа бичихээс өмнө дуудна
    // ---------------------------------------------------------

    public function testSetRoleGuardsBeforeConfigure(): void
    {
        $body = $this->methodBody('setRole');
        $this->assertCalledBefore($body, '$this->assertCanManageAccessOf($id)', '$this->configureRoles(', 'setRole');
        $this->assertCalledBefore($body, '$this->assertRoleChangesAllowed($id', '$this->configureRoles(', 'setRole');
    }

    public function testSetOrganizationGuardsBeforeConfigure(): void
    {
        $body = $this->methodBody('setOrganization');
        $this->assertCalledBefore($body, '$this->assertCanManageAccessOf($id)', '$this->configureOrgs(', 'setOrganization');
        $this->assertCalledBefore($body, '$this->assertOrganizationChangesAllowed($id', '$this->configureOrgs(', 'setOrganization');
    }

    public function testUpdateGuardsBeforeConfigure(): void
    {
        $body = $this->methodBody('update');
        $this->assertCalledBefore($body, '$this->assertOrganizationChangesAllowed($id', '$this->configureOrgs(', 'update');
        $this->assertCalledBefore($body, '$this->assertRoleChangesAllowed($id', '$this->configureRoles(', 'update');
        $this->assertCalledBefore($body, '$this->assertRoleChangesAllowed($id', '$model->updateById(', 'update');
    }

    public function testInsertChecksOrganizationScopeBeforeCreatingUser(): void
    {
        $body = $this->methodBody('insert');
        $this->assertCalledBefore($body, "'organization-outside-own-scope'", '$model->insert(', 'insert');
    }

    public function testSignupApproveChecksOrganizationScopeBeforeCreatingUser(): void
    {
        $body = $this->methodBody('signupApprove');
        $this->assertCalledBefore($body, "'organization-outside-own-scope'", '$model->insert(', 'signupApprove');
    }

    public function testChangeGuardsDelegateToAccessCheck(): void
    {
        $this->assertStringContainsString('$this->assertCanManageAccessOf($id)', $this->methodBody('assertRoleChangesAllowed'));
        $this->assertStringContainsString('$this->assertCanManageAccessOf($id)', $this->methodBody('assertOrganizationChangesAllowed'));
    }
}
