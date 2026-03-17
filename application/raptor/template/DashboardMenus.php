<?php

namespace Raptor\Template;

/**
 * Class DashboardMenus
 *
 * Dashboard sidebar-ийн анхдагч цэсний бүтэц.
 * MenuModel::__initial() дотроос дуудагдана.
 *
 * @package Raptor\Template
 */
class DashboardMenus
{
    /**
     * Dashboard-ийн үндсэн цэсний бүтцийг үүсгэх.
     *
     * Contents, Shop, System гэсэн 3 үндсэн хэсэгтэй.
     *
     * @param MenuModel $model
     */
    public static function seed(MenuModel $model): void
    {
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }

        /**
         * ----------------------------------------
         * 1. CONTENTS үндсэн хэсэг
         * ----------------------------------------
         */
        $contents = $model->insert(
            ['position' => '100'],
            ['mn' => ['title' => 'Агуулгууд'], 'en' => ['title' => 'Contents']]
        );
        if (isset($contents['id'])) {
            // Public веб сайт руу очих линк
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '110',
                    'alias' => 'system',
                    'icon' => 'bi bi-rocket-takeoff',
                    'href' => "$path/home\" target=\"__blank"
                ],
                ['mn' => ['title' => 'Веблүү очих'], 'en' => ['title' => 'Visit Website']]
            );
            // Мессежүүд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '115',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-chat-dots',
                    'href' => "$path/dashboard/messages"
                ],
                ['mn' => ['title' => 'Мессежүүд'], 'en' => ['title' => 'Messages']]
            );
            // Хуудсууд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '120',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-book-half',
                    'href' => "$path/dashboard/pages"
                ],
                ['mn' => ['title' => 'Хуудсууд'], 'en' => ['title' => 'Pages']]
            );
            // Мэдээнүүд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '130',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-newspaper',
                    'href' => "$path/dashboard/news"
                ],
                ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']]
            );
            // Сэтгэгдлүүд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '135',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-chat-left-text',
                    'href' => "$path/dashboard/comments"
                ],
                ['mn' => ['title' => 'Сэтгэгдлүүд'], 'en' => ['title' => 'Comments']]
            );
            // Файлууд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '140',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-folder',
                    'href' => "$path/dashboard/files"
                ],
                ['mn' => ['title' => 'Файлууд'], 'en' => ['title' => 'Files']]
            );
            // Localization
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '150',
                    'alias' => 'system',
                    'permission' => 'system_localization_index',
                    'icon' => 'bi bi-translate',
                    'href' => "$path/dashboard/localization"
                ],
                ['mn' => ['title' => 'Нутагшуулалт'], 'en' => ['title' => 'Localization']]
            );
            // Reference tables
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '160',
                    'alias' => 'system',
                    'permission' => 'system_templates_index',
                    'icon' => 'bi bi-layout-wtf',
                    'href' => "$path/dashboard/references"
                ],
                ['mn' => ['title' => 'Лавлах хүснэгтүүд'], 'en' => ['title' => 'Reference Tables']]
            );
            // Settings
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '170',
                    'alias' => 'system',
                    'permission' => 'system_content_settings',
                    'icon' => 'bi bi-gear-wide-connected',
                    'href' => "$path/dashboard/settings"
                ],
                ['mn' => ['title' => 'Тохируулгууд'], 'en' => ['title' => 'Settings']]
            );
        }

        /**
         * ----------------------------------------
         * 2. ХУДАЛДАА (Products & Orders)
         * ----------------------------------------
         */
        $shop = $model->insert(
            ['position' => '200'],
            ['mn' => ['title' => 'Дэлгүүр'], 'en' => ['title' => 'Shop']]
        );
        if (isset($shop['id'])) {
            // Бүтээгдэхүүнүүд
            $model->insert(
                [
                    'parent_id' => $shop['id'],
                    'position' => '210',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-box2-heart',
                    'href' => "$path/dashboard/products"
                ],
                ['mn' => ['title' => 'Бүтээгдэхүүн'], 'en' => ['title' => 'Products']]
            );
            // Захиалгууд
            $model->insert(
                [
                    'parent_id' => $shop['id'],
                    'position' => '220',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-cart3',
                    'href' => "$path/dashboard/orders"
                ],
                ['mn' => ['title' => 'Захиалгууд'], 'en' => ['title' => 'Orders']]
            );
        }

        /**
         * ----------------------------------------
         * 3. SYSTEM үндсэн хэсэг
         * ----------------------------------------
         */
        $system = $model->insert(
            ['position' => '300'],
            ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']]
        );
        if (isset($system['id'])) {
            // Хэрэглэгчид
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '310',
                    'permission' => 'system_user_index',
                    'icon' => 'bi bi-people-fill',
                    'href' => "$path/dashboard/users"
                ],
                ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Users']]
            );
            // Байгууллагууд
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '320',
                    'permission' => 'system_organization_index',
                    'icon' => 'bi bi-building',
                    'href' => "$path/dashboard/organizations"
                ],
                ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']]
            );
            // Logs
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '330',
                    'permission' => 'system_logger',
                    'icon' => 'bi bi-list-stars',
                    'href' => "$path/dashboard/logs"
                ],
                ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']]
            );
            // Хөгжүүлэлтийн хүсэлт
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '340',
                    'alias' => 'system',
                    'icon' => 'bi bi-code-slash',
                    'href' => "$path/dashboard/dev-requests"
                ],
                ['mn' => ['title' => 'Хөгжүүлэлтийн хүсэлт'], 'en' => ['title' => 'Dev Requests']]
            );
            // Гарын авлага
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '350',
                    'icon' => 'bi bi-book',
                    'href' => "$path/dashboard/manual"
                ],
                ['mn' => ['title' => 'Гарын авлага'], 'en' => ['title' => 'Manual']]
            );

            // Raptor Migrations & Цэс удирдах
            // permission => 'system_coder': system_coder нь role, permission биш.
            // Coder role-тэй хэрэглэгчид isUserCan() бүх утганд true буцаадаг
            // тул зөвхөн coder-д харагдана.
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '360',
                    'alias' => 'system',
                    'permission' => 'system_coder',
                    'icon' => 'bi bi-database-gear',
                    'href' => "$path/dashboard/migrations"
                ],
                ['mn' => ['title' => 'Database Migrations'], 'en' => ['title' => 'Database Migrations']]
            );
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '370',
                    'alias' => 'system',
                    'permission' => 'system_coder',
                    'icon' => 'bi bi-menu-button-wide-fill',
                    'href' => "$path/dashboard/manage/menu"
                ],
                ['mn' => ['title' => 'Цэс удирдах'], 'en' => ['title' => 'Manage Menu']]
            );
        }
    }
}
