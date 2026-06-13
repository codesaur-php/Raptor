<?php

namespace Raptor\Template;

/**
 * Class MenuSeed
 *
 * Dashboard sidebar-ийн анхдагч цэсний бүтэц.
 * MenuModel::__initial() дотроос дуудагдана.
 *
 * @package Raptor\Template
 */
class MenuSeed
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
        // MenuModel нь зөвхөн dashboard request үед ашиглагддаг тул mount хэсгийг
        // тухайн request-ийн URI-ийн эхний segment-ээс тодорхойлно. Base path ($path)
        // байвал эхэлж зүснэ. REQUEST_URI байхгүй (CLI/тест) бол default нь '/dashboard'.
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', \PHP_URL_PATH) ?: '';
        if ($path !== '' && \str_starts_with($uri, $path)) {
            $uri = \substr($uri, \strlen($path));
        }
        $segment = \explode('/', \trim($uri, '/'))[0] ?? '';
        $mount = '/' . ($segment !== '' ? $segment : 'dashboard');

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
                    'href' => "$path$mount/messages"
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
                    'href' => "$path$mount/pages"
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
                    'href' => "$path$mount/news"
                ],
                ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']]
            );
            // Файлууд
            $model->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '140',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-folder',
                    'href' => "$path$mount/files"
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
                    'href' => "$path$mount/localization"
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
                    'href' => "$path$mount/references"
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
                    'href' => "$path$mount/settings"
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
                    'href' => "$path$mount/products"
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
                    'href' => "$path$mount/orders"
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
            ['position' => '900'],
            ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']]
        );
        if (isset($system['id'])) {
            // Хэрэглэгчид
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '910',
                    'permission' => 'system_user_index',
                    'icon' => 'bi bi-people-fill',
                    'href' => "$path$mount/users"
                ],
                ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Users']]
            );
            // Байгууллагууд
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '920',
                    'permission' => 'system_organization_index',
                    'icon' => 'bi bi-building',
                    'href' => "$path$mount/organizations"
                ],
                ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']]
            );
            // Logs
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '930',
                    'permission' => 'system_logger',
                    'icon' => 'bi bi-list-stars',
                    'href' => "$path$mount/logs"
                ],
                ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']]
            );
            // Хөгжүүлэлтийн хүсэлт
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '940',
                    'alias' => 'system',
                    'icon' => 'bi bi-code-slash',
                    'href' => "$path$mount/dev-requests"
                ],
                ['mn' => ['title' => 'Хөгжүүлэлтийн хүсэлт'], 'en' => ['title' => 'Dev Requests']]
            );
            // Гарын авлага
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '950',
                    'icon' => 'bi bi-book',
                    'href' => "$path$mount/manual"
                ],
                ['mn' => ['title' => 'Гарын авлага'], 'en' => ['title' => 'Manual']]
            );

            // Raptor Migrations & Хогийн сав & Цэс удирдах
            // permission => 'system_coder': system_coder нь role, permission биш.
            // Coder role-тэй хэрэглэгчид isUserCan() бүх утганд true буцаадаг
            // тул зөвхөн coder-д харагдана.
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '960',
                    'alias' => 'system',
                    'permission' => 'system_coder',
                    'icon' => 'bi bi-database-gear',
                    'href' => "$path$mount/migrations"
                ],
                ['mn' => ['title' => 'Database Migrations'], 'en' => ['title' => 'Database Migrations']]
            );
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '970',
                    'alias' => 'system',
                    'permission' => 'system_coder',
                    'icon' => 'bi bi-trash3',
                    'href' => "$path$mount/trash"
                ],
                ['mn' => ['title' => 'Хогийн сав'], 'en' => ['title' => 'Trash']]
            );
            $model->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '980',
                    'alias' => 'system',
                    'permission' => 'system_coder',
                    'icon' => 'bi bi-menu-button-wide-fill',
                    'href' => "$path$mount/manage/menu"
                ],
                ['mn' => ['title' => 'Цэс удирдах'], 'en' => ['title' => 'Manage Menu']]
            );
        }
    }
}
