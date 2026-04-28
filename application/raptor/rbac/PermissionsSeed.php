<?php

namespace Raptor\RBAC;

/**
 * Class PermissionsSeed
 *
 * Системийн үндсэн модулиудад шаардлагатай анхны permission-уудыг
 * seed хийнэ. Permissions::__initial() дотроос дуудагдана.
 *
 * @package Raptor\RBAC
 */
class PermissionsSeed
{
    /**
     * Анхны permission-уудыг insert хийх.
     *
     * @param string $table Permission хүснэгтийн нэр
     * @param \PDO $pdo
     */
    public static function seed(string $table, \PDO $pdo): void
    {
        $permissions = [
            // Log
            ['module' => 'log',          'name' => 'logger',                'description' => 'View system access logs and activity history'],

            // RBAC
            ['module' => 'user',         'name' => 'rbac',                  'description' => 'Manage roles and assign permissions to users'],

            // User
            ['module' => 'user',         'name' => 'user_index',            'description' => 'View the list of registered users'],
            ['module' => 'user',         'name' => 'user_insert',           'description' => 'Create new user accounts'],
            ['module' => 'user',         'name' => 'user_update',           'description' => 'Edit existing user profiles and settings'],
            ['module' => 'user',         'name' => 'user_delete',           'description' => 'Delete user accounts from the system'],
            ['module' => 'user',         'name' => 'user_organization_set', 'description' => 'Assign or change a user organization'],

            // Organization
            ['module' => 'organization', 'name' => 'organization_index',    'description' => 'View the list of organizations'],
            ['module' => 'organization', 'name' => 'organization_insert',   'description' => 'Create new organizations'],
            ['module' => 'organization', 'name' => 'organization_update',   'description' => 'Edit existing organization details'],
            ['module' => 'organization', 'name' => 'organization_delete',   'description' => 'Delete organizations from the system'],

            // Content
            ['module' => 'content',      'name' => 'content_settings',      'description' => 'Manage site content settings and configuration'],
            ['module' => 'content',      'name' => 'content_index',         'description' => 'View the list of content pages news and files'],
            ['module' => 'content',      'name' => 'content_insert',        'description' => 'Create new content entries'],
            ['module' => 'content',      'name' => 'content_update',        'description' => 'Edit existing content entries'],
            ['module' => 'content',      'name' => 'content_publish',       'description' => 'Publish or unpublish content'],
            ['module' => 'content',      'name' => 'content_delete',        'description' => 'Delete content entries'],

            // Product
            ['module' => 'product',      'name' => 'product_index',         'description' => 'View the list of products and orders'],
            ['module' => 'product',      'name' => 'product_insert',        'description' => 'Create new product entries'],
            ['module' => 'product',      'name' => 'product_update',        'description' => 'Edit existing products and update order status'],
            ['module' => 'product',      'name' => 'product_publish',       'description' => 'Publish or unpublish products'],
            ['module' => 'product',      'name' => 'product_delete',        'description' => 'Delete products and orders'],

            // Localization
            ['module' => 'localization', 'name' => 'localization_index',    'description' => 'View localization and translation entries'],
            ['module' => 'localization', 'name' => 'localization_insert',   'description' => 'Add new translation entries'],
            ['module' => 'localization', 'name' => 'localization_update',   'description' => 'Edit existing translation entries'],
            ['module' => 'localization', 'name' => 'localization_delete',   'description' => 'Delete translation entries'],

            // Template / Reference
            ['module' => 'template',     'name' => 'templates_index',       'description' => 'View and manage reference tables'],

            // Development
            ['module' => 'development',  'name' => 'development',           'description' => 'Manage all development requests and respond to others'],
        ];

        $stmt = $pdo->prepare(
            "INSERT INTO $table (created_at, alias, module, name, description) VALUES (:created_at, 'system', :module, :name, :description)"
        );

        $now = \date('Y-m-d H:i:s');
        foreach ($permissions as $perm) {
            $stmt->execute([
                ':created_at'  => $now,
                ':module'      => $perm['module'],
                ':name'        => $perm['name'],
                ':description' => $perm['description'],
            ]);
        }
    }
}
