<?php

namespace Raptor\RBAC;

/**
 * Class RolePermissionSeed
 *
 * Роль үүсгэж, permission оноох seed.
 * RolePermission::__initial() дотроос дуудагдана.
 *
 * Дүрүүдийн эрхийн хуваарилалт:
 *  - coder   - бүх эрхтэй (Roles::__initial() дээр үүснэ, seed шаардахгүй)
 *  - admin   - бүх permission
 *  - manager - хэрэглэгч, байгууллага, контент, бүтээгдэхүүн, орчуулга, development
 *  - editor  - контент, бүтээгдэхүүн үүсгэх/засах/нийтлэх
 *  - viewer  - зөвхөн харах
 *
 * @package Raptor\RBAC
 */
class RolePermissionSeed
{
    /**
     * @param string $table     RolePermission хүснэгтийн нэр
     * @param string $roles     Roles хүснэгтийн нэр
     * @param string $perms     Permissions хүснэгтийн нэр
     * @param \PDO   $pdo
     */
    public static function seed(string $table, string $roles, string $perms, \PDO $pdo): void
    {
        $now = \date('Y-m-d H:i:s');

        // Роль үүсгэх (coder-ээс бусад, coder нь Roles::__initial() дээр үүссэн)
        $roleDefinitions = [
            'admin'   => 'Full management access except developer tools',
            'manager' => 'Manage users content and localization',
            'editor'  => 'Create and edit content entries',
            'viewer'  => 'Read-only access to content and localization',
        ];
        $insertRole = $pdo->prepare(
            "INSERT INTO $roles (created_at, name, description, alias) VALUES (:now, :name, :desc, 'system')"
        );
        foreach ($roleDefinitions as $name => $desc) {
            // Байгаа эсэхийг шалгаж, байхгүй бол үүсгэнэ
            $check = $pdo->prepare("SELECT id FROM $roles WHERE name = :name");
            $check->execute([':name' => $name]);
            if (!$check->fetch()) {
                $insertRole->execute([':now' => $now, ':name' => $name, ':desc' => $desc]);
            }
        }

        // admin: бүх permission
        $pdo->prepare("
            INSERT INTO $table (created_at, role_id, permission_id, alias)
            SELECT :now, r.id, p.id, p.alias
            FROM $roles r, $perms p
            WHERE r.name = 'admin'
        ")->execute([':now' => $now]);

        // manager
        self::assignPermissions($pdo, $table, $roles, $perms, $now, 'manager', [
            'logger',
            'user_index', 'user_insert', 'user_update', 'user_organization_set',
            'organization_index', 'organization_update',
            'content_settings', 'content_index', 'content_insert',
            'content_update', 'content_publish', 'content_delete',
            'product_index', 'product_insert', 'product_update',
            'product_publish', 'product_delete',
            'localization_index', 'localization_insert', 'localization_update',
            'templates_index',
            'development',
        ]);

        // editor
        self::assignPermissions($pdo, $table, $roles, $perms, $now, 'editor', [
            'content_index', 'content_insert', 'content_update', 'content_publish',
            'product_index', 'product_insert', 'product_update', 'product_publish',
            'localization_index',
            'templates_index',
        ]);

        // viewer
        self::assignPermissions($pdo, $table, $roles, $perms, $now, 'viewer', [
            'content_index',
            'product_index',
            'localization_index',
        ]);
    }

    /**
     * Тодорхой роль-д permission-уудыг оноох.
     */
    private static function assignPermissions(
        \PDO $pdo, string $table, string $roles, string $perms,
        string $now, string $roleName, array $permNames
    ): void {
        $placeholders = \implode(',', \array_map(fn($i) => ":p$i", \array_keys($permNames)));
        $stmt = $pdo->prepare("
            INSERT INTO $table (created_at, role_id, permission_id, alias)
            SELECT :now, r.id, p.id, p.alias
            FROM $roles r, $perms p
            WHERE r.name = :role AND p.name IN ($placeholders)
        ");
        $params = [':now' => $now, ':role' => $roleName];
        foreach ($permNames as $i => $name) {
            $params[":p$i"] = $name;
        }
        $stmt->execute($params);
    }
}
