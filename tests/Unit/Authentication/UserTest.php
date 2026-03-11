<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;
use Raptor\Authentication\User;

class UserTest extends RaptorTestCase
{
    // ===== is() method tests =====

    public function testCoderIsAnyRole(): void
    {
        $user = $this->createCoder();

        $this->assertTrue($user->is('system_coder'));
        $this->assertTrue($user->is('system_admin'));
        $this->assertTrue($user->is('nonexistent_role'));
    }

    public function testAdminIsOwnRole(): void
    {
        $user = $this->createAdmin();

        $this->assertTrue($user->is('system_admin'));
    }

    public function testAdminIsNotCoderRole(): void
    {
        $user = $this->createAdmin();

        $this->assertFalse($user->is('system_coder'));
        $this->assertFalse($user->is('system_manager'));
    }

    public function testGuestHasNoRoles(): void
    {
        $user = $this->createGuest();

        $this->assertFalse($user->is('system_coder'));
        $this->assertFalse($user->is('system_admin'));
    }

    // ===== can() method tests =====

    public function testCoderCanDoAnything(): void
    {
        $user = $this->createCoder();

        $this->assertTrue($user->can('system_content_index'));
        $this->assertTrue($user->can('system_user_delete'));
        $this->assertTrue($user->can('any_random_permission'));
    }

    public function testAdminCanGrantedPermission(): void
    {
        $user = $this->createAdmin();

        $this->assertTrue($user->can('system_content_index'));
        $this->assertTrue($user->can('system_user_update'));
        $this->assertTrue($user->can('system_logger'));
    }

    public function testAdminCannotUngrantedPermission(): void
    {
        $user = $this->createAdmin();

        $this->assertFalse($user->can('system_rbac'));
        $this->assertFalse($user->can('nonexistent_permission'));
    }

    public function testCanWithSpecificRole(): void
    {
        $user = $this->createUser([
            'system_admin'   => ['system_content_index' => true],
            'system_manager' => ['system_user_index' => true],
        ]);

        // system_admin роль дотор шалгах
        $this->assertTrue($user->can('system_content_index', 'system_admin'));
        $this->assertFalse($user->can('system_user_index', 'system_admin'));

        // system_manager роль дотор шалгах
        $this->assertTrue($user->can('system_user_index', 'system_manager'));
        $this->assertFalse($user->can('system_content_index', 'system_manager'));
    }

    public function testCanWithNonexistentRoleReturnsFalse(): void
    {
        $user = $this->createAdmin();

        $this->assertFalse($user->can('system_content_index', 'nonexistent_role'));
    }

    public function testGuestCannotDoAnything(): void
    {
        $user = $this->createGuest();

        $this->assertFalse($user->can('system_content_index'));
        $this->assertFalse($user->can('system_logger'));
    }

    // ===== Profile & Organization =====

    public function testProfileAccess(): void
    {
        $user = $this->createUser([], ['id' => 42, 'username' => 'johndoe']);

        $this->assertEquals(42, $user->profile['id']);
        $this->assertEquals('johndoe', $user->profile['username']);
    }

    public function testOrganizationAccess(): void
    {
        $user = $this->createUser([], [], ['id' => 5, 'name' => 'My Org']);

        $this->assertEquals(5, $user->organization['id']);
        $this->assertEquals('My Org', $user->organization['name']);
    }
}
