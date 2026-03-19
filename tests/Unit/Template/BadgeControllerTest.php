<?php

namespace Tests\Unit\Template;

use Tests\Support\RaptorTestCase;
use Raptor\Template\BadgeController;

/**
 * BadgeController unit test.
 *
 * DB-д хандахгүйгээр BADGE_MAP, PERMISSION_MAP бүтцийн зөв байдал,
 * permission шалгах логик зэргийг тестлэнэ.
 */
class BadgeControllerTest extends RaptorTestCase
{
    // ---------------------------------------------------------
    // BADGE_MAP structure validation
    // ---------------------------------------------------------

    public function testBadgeMapIsNotEmpty(): void
    {
        $this->assertNotEmpty(BadgeController::BADGE_MAP);
    }

    public function testBadgeMapHasCorrectStructure(): void
    {
        foreach (BadgeController::BADGE_MAP as $logTable => $actions) {
            $this->assertIsString($logTable, "Log table key must be a string");
            $this->assertNotEmpty($logTable, "Log table key must not be empty");
            $this->assertIsArray($actions, "Actions for '$logTable' must be an array");
            $this->assertNotEmpty($actions, "Actions for '$logTable' must not be empty");

            foreach ($actions as $action => $config) {
                $this->assertIsString($action, "Action key in '$logTable' must be a string");
                $this->assertNotEmpty($action, "Action key in '$logTable' must not be empty");
                $this->assertIsArray($config, "Config for '$logTable.$action' must be an array");
                $this->assertCount(2, $config, "Config for '$logTable.$action' must have exactly 2 elements [path, color]");
                $this->assertIsString($config[0], "Module path for '$logTable.$action' must be a string");
                $this->assertIsString($config[1], "Color for '$logTable.$action' must be a string");
                $this->assertStringStartsWith('/dashboard/', $config[0], "Module path for '$logTable.$action' must start with /dashboard/");
            }
        }
    }

    // ---------------------------------------------------------
    // Badge colors are valid
    // ---------------------------------------------------------

    public function testBadgeColorsAreValid(): void
    {
        $validColors = ['green', 'blue', 'red', 'info'];

        foreach (BadgeController::BADGE_MAP as $logTable => $actions) {
            foreach ($actions as $action => [$module, $color]) {
                $this->assertContains(
                    $color,
                    $validColors,
                    "Invalid badge color '$color' for '$logTable.$action'. Expected one of: " . implode(', ', $validColors)
                );
            }
        }
    }

    /**
     * Green = create/insert, Blue = update, Red = delete/deactivate, Info = comment/review
     */
    public function testCreateActionsAreGreen(): void
    {
        $createActions = ['create', 'contact-send', 'files-upload', 'language-create',
            'text-create', 'reference-create', 'store', 'signup-approve',
            'rbac-create-role', 'rbac-create-permission', 'template-menu-create'];

        foreach (BadgeController::BADGE_MAP as $logTable => $actions) {
            foreach ($actions as $action => [$module, $color]) {
                if (in_array($action, $createActions, true)) {
                    $this->assertEquals(
                        'green', $color,
                        "Create action '$action' in '$logTable' should be green, got '$color'"
                    );
                }
            }
        }
    }

    public function testDeactivateActionsAreRed(): void
    {
        foreach (BadgeController::BADGE_MAP as $logTable => $actions) {
            foreach ($actions as $action => [$module, $color]) {
                if ($action === 'deactivate') {
                    $this->assertEquals(
                        'red', $color,
                        "Deactivate action in '$logTable' should be red, got '$color'"
                    );
                }
            }
        }
    }

    // ---------------------------------------------------------
    // PERMISSION_MAP has entries for all modules in BADGE_MAP
    // ---------------------------------------------------------

    public function testPermissionMapCoversAllBadgeModules(): void
    {
        // Collect all unique module paths from BADGE_MAP
        $badgeModules = [];
        foreach (BadgeController::BADGE_MAP as $actions) {
            foreach ($actions as [$module, $color]) {
                $badgeModules[$module] = true;
            }
        }

        foreach (array_keys($badgeModules) as $module) {
            $this->assertArrayHasKey(
                $module,
                BadgeController::PERMISSION_MAP,
                "Module '$module' is in BADGE_MAP but missing from PERMISSION_MAP"
            );
        }
    }

    public function testPermissionMapIsNotEmpty(): void
    {
        $this->assertNotEmpty(BadgeController::PERMISSION_MAP);
    }

    public function testPermissionMapValuesAreValidFormat(): void
    {
        foreach (BadgeController::PERMISSION_MAP as $module => $permission) {
            $this->assertIsString($module, "PERMISSION_MAP key must be a string");
            $this->assertStringStartsWith('/dashboard/', $module, "Module '$module' must start with /dashboard/");

            // Permission is null, 'role:xxx', or a permission string
            if ($permission !== null) {
                $this->assertIsString($permission, "Permission for '$module' must be string or null");
                $this->assertNotEmpty($permission, "Permission for '$module' must not be empty string");
            }
        }
    }

    // ---------------------------------------------------------
    // PERMISSION_MAP role: prefix format
    // ---------------------------------------------------------

    public function testRolePrefixPermissionsAreWellFormed(): void
    {
        foreach (BadgeController::PERMISSION_MAP as $module => $permission) {
            if ($permission !== null && str_starts_with($permission, 'role:')) {
                $roleName = substr($permission, 5);
                $this->assertNotEmpty($roleName, "Role name after 'role:' prefix must not be empty for module '$module'");
                $this->assertStringStartsWith('system_', $roleName, "Role name '$roleName' should start with system_ for module '$module'");
            }
        }
    }

    // ---------------------------------------------------------
    // Permission check logic (hasModuleAccess)
    // ---------------------------------------------------------

    /**
     * Test the permission check logic that hasModuleAccess uses.
     * Since hasModuleAccess is private, we test the underlying logic.
     */
    public function testNullPermissionMeansAnyAuthenticated(): void
    {
        // /dashboard/manual has null permission
        $permission = BadgeController::PERMISSION_MAP['/dashboard/manual'];
        $this->assertNull($permission, 'Manual module should have null permission (any authenticated admin)');
    }

    public function testRolePermissionFormat(): void
    {
        // /dashboard/manage/menu requires 'role:system_coder'
        $permission = BadgeController::PERMISSION_MAP['/dashboard/manage/menu'];
        $this->assertNotNull($permission);
        $this->assertTrue(str_starts_with($permission, 'role:'));
        $this->assertEquals('system_coder', substr($permission, 5));
    }

    public function testStandardPermissionFormat(): void
    {
        // /dashboard/news requires 'system_content_index'
        $permission = BadgeController::PERMISSION_MAP['/dashboard/news'];
        $this->assertNotNull($permission);
        $this->assertFalse(str_starts_with($permission, 'role:'));
        $this->assertEquals('system_content_index', $permission);
    }

    // ---------------------------------------------------------
    // BADGE_MAP module grouping consistency
    // ---------------------------------------------------------

    public function testEachLogTableHasConsistentModuleForBasicActions(): void
    {
        // For basic CRUD actions (create, update, deactivate) within a single log table,
        // they should point to the same module
        $basicActions = ['create', 'update', 'deactivate'];

        foreach (BadgeController::BADGE_MAP as $logTable => $actions) {
            $modules = [];
            foreach ($basicActions as $action) {
                if (isset($actions[$action])) {
                    $modules[$action] = $actions[$action][0];
                }
            }

            // If the table has at least 2 basic actions, they should point to the same module
            $uniqueModules = array_unique(array_values($modules));
            if (count($modules) >= 2) {
                $this->assertCount(
                    1, $uniqueModules,
                    "Basic CRUD actions in '$logTable' should point to the same module, " .
                    "found: " . implode(', ', $uniqueModules)
                );
            }
        }
    }

    // ---------------------------------------------------------
    // File-count badge modules exist in PERMISSION_MAP
    // ---------------------------------------------------------

    public function testManualModuleInPermissionMap(): void
    {
        $this->assertArrayHasKey('/dashboard/manual', BadgeController::PERMISSION_MAP);
    }

    public function testMigrationsModuleInPermissionMap(): void
    {
        $this->assertArrayHasKey('/dashboard/migrations', BadgeController::PERMISSION_MAP);
    }

    public function testMigrationsRequiresCoderRole(): void
    {
        $this->assertEquals(
            'role:system_coder',
            BadgeController::PERMISSION_MAP['/dashboard/migrations']
        );
    }
}
