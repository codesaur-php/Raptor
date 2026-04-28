<?php

namespace Tests\Unit\Content;

use PHPUnit\Framework\TestCase;

/**
 * Бүх delete method-ууд TrashModel::store() дуудаж байгаа эсэхийг шалгана.
 * Source code analysis - DB шаардахгүй.
 */
class DeleteTrashIntegrationTest extends TestCase
{
    /**
     * TrashModel::store() дуудагдаж байгаа controller-уудын жагсаалт.
     *
     * `module` нь `$this->log()`-ийн log channel нэртэй адил байх ёстой:
     * trash-аас restore хийхэд module-ийг шууд log table болгож ашигладаг.
     */
    public static function deleteControllersProvider(): array
    {
        return [
            ['raptor/localization/text/TextController.php', 'content'],
            ['raptor/content/news/NewsController.php', 'news'],
            ['raptor/content/page/PagesController.php', 'pages'],
            ['raptor/content/reference/ReferencesController.php', 'content'],
            ['raptor/content/file/FilesController.php', 'files'],
            ['raptor/template/TemplateController.php', 'dashboard'],
            ['dashboard/shop/OrdersController.php', 'products_orders'],
            ['dashboard/shop/ProductsController.php', 'products'],
            ['raptor/content/news/CommentsController.php', 'news'],
            ['dashboard/shop/ReviewsController.php', 'products'],
            ['raptor/development/DevRequestController.php', 'dev_requests'],
            ['raptor/content/messages/MessagesController.php', 'messages'],
            ['raptor/localization/language/LanguageController.php', 'content'],
        ];
    }

    /**
     * @dataProvider deleteControllersProvider
     */
    public function testControllerStoresInTrashAfterDelete(string $file, string $module): void
    {
        $path = \dirname(__DIR__, 3) . '/application/' . $file;
        $this->assertFileExists($path, "Controller file must exist: $file");

        $source = \file_get_contents($path);

        $this->assertStringContainsString(
            'TrashModel($this->pdo))->store(',
            $source,
            "Controller $file must call TrashModel::store()"
        );

        $this->assertStringContainsString(
            "'$module'",
            $source,
            "Controller $file must use module name '$module' (= log channel) for trash storage"
        );

        // deleteById() must come BEFORE store() - trash only stores actually deleted records
        $deletePos = \strpos($source, 'deleteById(');
        $storePos = \strpos($source, "TrashModel(\$this->pdo))->store(\n                '$module'");
        if ($storePos === false) {
            $storePos = \strpos($source, "TrashModel(\$this->pdo))->store(\r\n                '$module'");
        }
        if ($deletePos !== false && $storePos !== false) {
            $this->assertLessThan(
                $storePos,
                $deletePos,
                "Controller $file must call deleteById() BEFORE TrashModel::store(). " .
                "Trash should only store records that were actually deleted."
            );
        }
    }

    /**
     * Attachment delete-ууд ч мөн parent module-ийн log channel-аар trash-д хадгалдаг эсэх.
     */
    public static function attachmentControllersProvider(): array
    {
        return [
            ['raptor/content/news/NewsController.php', 'news'],
            ['raptor/content/page/PagesController.php', 'pages'],
            ['dashboard/shop/ProductsController.php', 'products'],
        ];
    }

    /**
     * @dataProvider attachmentControllersProvider
     */
    public function testAttachmentDeleteStoresInTrash(string $file, string $module): void
    {
        $path = \dirname(__DIR__, 3) . '/application/' . $file;
        $source = \file_get_contents($path);

        $this->assertStringContainsString(
            "'$module'",
            $source,
            "Controller $file must store attachment deletes in trash with module '$module' (parent log channel)"
        );
    }

    /**
     * Бүх delete controller-ууд deactivateById АШИГЛАХГҮЙ байх ёстой
     * (Users, Organization-аас бусад).
     */
    public static function noDeactivateProvider(): array
    {
        return [
            ['raptor/localization/text/TextController.php'],
            ['raptor/content/news/NewsController.php'],
            ['raptor/content/page/PagesController.php'],
            ['raptor/content/reference/ReferencesController.php'],
            ['raptor/content/file/FilesController.php'],
            ['raptor/template/TemplateController.php'],
            ['dashboard/shop/OrdersController.php'],
            ['dashboard/shop/ProductsController.php'],
            ['raptor/content/news/CommentsController.php'],
            ['dashboard/shop/ReviewsController.php'],
            ['raptor/development/DevRequestController.php'],
            ['raptor/content/messages/MessagesController.php'],
        ];
    }

    /**
     * @dataProvider noDeactivateProvider
     */
    public function testControllerDoesNotUseDeactivateById(string $file): void
    {
        $path = \dirname(__DIR__, 3) . '/application/' . $file;
        $source = \file_get_contents($path);

        $this->assertStringNotContainsString(
            'deactivateById',
            $source,
            "Controller $file must NOT use deactivateById - should use deleteById instead"
        );
    }

    /**
     * Users/Organization-д deactivateById БАЙХ ёстой.
     */
    public function testUsersControllerKeepsDeactivate(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/user/UsersController.php'
        );
        $this->assertStringContainsString('deactivateById', $source);
    }

    public function testOrganizationControllerKeepsDeactivate(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/organization/OrganizationController.php'
        );
        $this->assertStringContainsString('deactivateById', $source);
    }
}
