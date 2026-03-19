<?php

namespace Tests\Unit\Controller;

use Tests\Support\RaptorTestCase;
use Raptor\Authentication\User;

/**
 * Controller permission, auth, getUserId тестүүд.
 *
 * Controller нь abstract тул anonymous class-аар конкрет instance үүсгэнэ.
 * respondJSON()-г CLI-д шалгахад headers_sent() нь true байж болох тул
 * output capture ашиглан тестлэнэ.
 */
class ControllerPermissionTest extends RaptorTestCase
{
    /**
     * Controller instance үүсгэх helper.
     *
     * @param User|null $user  Нэвтэрсэн хэрэглэгч (null = guest)
     * @param array $extraAttributes  Нэмэлт request attributes
     */
    private function createController(?User $user = null, array $extraAttributes = []): object
    {
        $attributes = array_merge([
            'pdo'  => $this->createMock(\PDO::class),
            'user' => $user,
        ], $extraAttributes);

        $request = $this->createMockRequest($attributes);

        return new class($request) extends \Raptor\Controller {};
    }

    // ---------------------------------------------------------
    // isUserAuthorized()
    // ---------------------------------------------------------

    public function testIsUserAuthorizedWithUser(): void
    {
        $user = $this->createUser([]);
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUserAuthorized());
    }

    public function testIsUserAuthorizedWithoutUser(): void
    {
        $controller = $this->createController(null);

        $this->assertFalse($controller->isUserAuthorized());
    }

    public function testIsUserAuthorizedWithCoder(): void
    {
        $user = $this->createCoder();
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUserAuthorized());
    }

    // ---------------------------------------------------------
    // getUserId()
    // ---------------------------------------------------------

    public function testGetUserIdReturnsCorrectId(): void
    {
        $user = $this->createUser([], ['id' => 42]);
        $controller = $this->createController($user);

        $this->assertEquals(42, $controller->getUserId());
    }

    public function testGetUserIdReturnsNullWhenNoUser(): void
    {
        $controller = $this->createController(null);

        $this->assertNull($controller->getUserId());
    }

    public function testGetUserIdDefaultValue(): void
    {
        $user = $this->createUser([]);
        $controller = $this->createController($user);

        // Default profile id is 1 (from RaptorTestCase::createUser)
        $this->assertEquals(1, $controller->getUserId());
    }

    // ---------------------------------------------------------
    // isUserCan() - RBAC permission checks
    // ---------------------------------------------------------

    public function testIsUserCanWithPermission(): void
    {
        $user = $this->createAdmin();
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUserCan('system_content_index'));
    }

    public function testIsUserCanWithoutPermission(): void
    {
        $user = $this->createAdmin();
        $controller = $this->createController($user);

        $this->assertFalse($controller->isUserCan('system_nonexistent_permission'));
    }

    public function testIsUserCanCoderHasAllPermissions(): void
    {
        $user = $this->createCoder();
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUserCan('system_content_index'));
        $this->assertTrue($controller->isUserCan('system_user_delete'));
        $this->assertTrue($controller->isUserCan('any_permission_at_all'));
    }

    public function testIsUserCanGuestHasNoPermissions(): void
    {
        $user = $this->createGuest();
        $controller = $this->createController($user);

        $this->assertFalse($controller->isUserCan('system_content_index'));
        $this->assertFalse($controller->isUserCan('system_user_index'));
    }

    public function testIsUserCanWithNullUser(): void
    {
        $controller = $this->createController(null);

        $this->assertFalse($controller->isUserCan('system_content_index'));
    }

    // ---------------------------------------------------------
    // isUser() - role checks
    // ---------------------------------------------------------

    public function testIsUserWithMatchingRole(): void
    {
        $user = $this->createAdmin();
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUser('system_admin'));
    }

    public function testIsUserWithoutMatchingRole(): void
    {
        $user = $this->createAdmin();
        $controller = $this->createController($user);

        $this->assertFalse($controller->isUser('system_coder'));
    }

    public function testIsUserCoderMatchesAllRoles(): void
    {
        $user = $this->createCoder();
        $controller = $this->createController($user);

        $this->assertTrue($controller->isUser('system_coder'));
        $this->assertTrue($controller->isUser('system_admin'));
        $this->assertTrue($controller->isUser('system_manager'));
        $this->assertTrue($controller->isUser('any_role'));
    }

    public function testIsUserGuestMatchesNoRoles(): void
    {
        $user = $this->createGuest();
        $controller = $this->createController($user);

        $this->assertFalse($controller->isUser('system_admin'));
        $this->assertFalse($controller->isUser('system_coder'));
    }

    public function testIsUserWithNullUser(): void
    {
        $controller = $this->createController(null);

        $this->assertFalse($controller->isUser('system_admin'));
    }

    // ---------------------------------------------------------
    // getUser()
    // ---------------------------------------------------------

    public function testGetUserReturnsUserInstance(): void
    {
        $user = $this->createUser([]);
        $controller = $this->createController($user);

        $this->assertInstanceOf(User::class, $controller->getUser());
    }

    public function testGetUserReturnsNullWhenNoUser(): void
    {
        $controller = $this->createController(null);

        $this->assertNull($controller->getUser());
    }

    public function testGetUserProfileData(): void
    {
        $user = $this->createUser([], [
            'id'         => 99,
            'username'   => 'testadmin',
            'email'      => 'admin@test.com',
            'first_name' => 'Admin',
            'last_name'  => 'Test',
        ]);
        $controller = $this->createController($user);

        $profile = $controller->getUser()->profile;
        $this->assertEquals(99, $profile['id']);
        $this->assertEquals('testadmin', $profile['username']);
        $this->assertEquals('admin@test.com', $profile['email']);
        $this->assertEquals('Admin', $profile['first_name']);
        $this->assertEquals('Test', $profile['last_name']);
    }

    // ---------------------------------------------------------
    // respondJSON() output format
    // ---------------------------------------------------------

    public function testRespondJsonOutputsValidJson(): void
    {
        $controller = $this->createController(null);

        ob_start();
        $controller->respondJSON(['status' => 'success', 'message' => 'OK']);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals('success', $decoded['status']);
        $this->assertEquals('OK', $decoded['message']);
    }

    public function testRespondJsonWithEmptyArray(): void
    {
        $controller = $this->createController(null);

        ob_start();
        $controller->respondJSON([]);
        $output = ob_get_clean();

        $this->assertJson($output);
        $this->assertEquals('[]', trim($output));
    }

    public function testRespondJsonWithNestedData(): void
    {
        $controller = $this->createController(null);

        $data = [
            'status' => 'success',
            'data'   => [
                'items' => [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ],
                'total' => 2,
            ],
        ];

        ob_start();
        $controller->respondJSON($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertEquals(2, $decoded['data']['total']);
        $this->assertCount(2, $decoded['data']['items']);
    }

    public function testRespondJsonWithErrorCode(): void
    {
        $controller = $this->createController(null);

        // In CLI, headers_sent() behavior varies but the JSON output is still correct
        ob_start();
        $controller->respondJSON(['status' => 'error', 'message' => 'Not found'], 404);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertEquals('error', $decoded['status']);
        $this->assertEquals('Not found', $decoded['message']);
    }

    public function testRespondJsonWithStringCode(): void
    {
        $controller = $this->createController(null);

        // String code should be ignored (not set as HTTP status)
        ob_start();
        $controller->respondJSON(['status' => 'error'], 'invalid-email');
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertEquals('error', $decoded['status']);
    }

    public function testRespondJsonWithZeroCode(): void
    {
        $controller = $this->createController(null);

        // Code 0 means default (200 OK), no status code change
        ob_start();
        $controller->respondJSON(['status' => 'success']);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertEquals('success', $decoded['status']);
    }

    // ---------------------------------------------------------
    // getLanguageCode() / getLanguages() (complementing ControllerTextTest)
    // ---------------------------------------------------------

    public function testGetLanguageCodeWithLocalization(): void
    {
        $controller = $this->createController(null, [
            'localization' => ['code' => 'mn', 'text' => []],
        ]);

        $this->assertEquals('mn', $controller->getLanguageCode());
    }

    public function testGetLanguageCodeWithoutLocalization(): void
    {
        $controller = $this->createController(null);

        $this->assertEquals('', $controller->getLanguageCode());
    }

    // ---------------------------------------------------------
    // Multiple roles with different permissions
    // ---------------------------------------------------------

    public function testUserWithMultipleRolesCanAccessAnyRolePermission(): void
    {
        $user = $this->createUser([
            'system_editor' => [
                'system_content_index'  => true,
                'system_content_insert' => true,
            ],
            'system_manager' => [
                'system_user_index'  => true,
                'system_user_update' => true,
            ],
        ]);
        $controller = $this->createController($user);

        // Can access permissions from both roles
        $this->assertTrue($controller->isUserCan('system_content_index'));
        $this->assertTrue($controller->isUserCan('system_user_index'));

        // Has both roles
        $this->assertTrue($controller->isUser('system_editor'));
        $this->assertTrue($controller->isUser('system_manager'));

        // Does not have unassigned role
        $this->assertFalse($controller->isUser('system_admin'));
    }
}
