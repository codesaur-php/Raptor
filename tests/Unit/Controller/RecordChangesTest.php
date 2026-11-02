<?php

namespace Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

use Dashboard\Controller;

/**
 * Controller::recordChanges() - update лог-ийн 'changes' (зөвхөн өөрчлөгдсөн
 * талбар, old -> new) зөв гарч байгааг шалгана.
 */
class RecordChangesTest extends TestCase
{
    private function changes(array $before, array $after, ?array $ignore = null): array
    {
        $controller = (new \ReflectionClass(
            new class extends Controller { public function __construct() {} }
        ))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Controller::class, 'recordChanges');
        $args = $ignore === null ? [$before, $after] : [$before, $after, $ignore];
        return $method->invoke($controller, ...$args);
    }

    public function testOnlyChangedFieldsWithOldAndNew(): void
    {
        $before = ['id' => 7, 'title' => 'Old', 'content' => '<p>same</p>', 'published' => '0'];
        $after  = ['id' => 7, 'title' => 'New', 'content' => '<p>same</p>', 'published' => 1];

        $this->assertSame(
            ['title' => ['old' => 'Old', 'new' => 'New'], 'published' => ['old' => '0', 'new' => 1]],
            $this->changes($before, $after)
        );
    }

    public function testLooseEqualityIgnoresTypeOnlyDifferences(): void
    {
        $this->assertSame([], $this->changes(
            ['position' => '5', 'href' => null],
            ['position' => 5, 'href' => '']
        ));
    }

    public function testUpdatedMetadataIgnored(): void
    {
        $this->assertSame([], $this->changes(
            ['updated_at' => '2026-01-01 00:00:00', 'updated_by' => 1],
            ['updated_at' => '2026-11-02 10:00:00', 'updated_by' => 2]
        ));
    }

    public function testNestedLocalizedContent(): void
    {
        $before = ['localized' => ['mn' => ['title' => 'Цэс'], 'en' => ['title' => 'Menu']]];
        $after  = ['localized' => ['mn' => ['title' => 'Цэс'], 'en' => ['title' => 'Menus']]];

        $this->assertSame(
            ['localized' => ['en' => ['title' => ['old' => 'Menu', 'new' => 'Menus']]]],
            $this->changes($before, $after)
        );
    }

    public function testNewFieldReportedWithNullOld(): void
    {
        $this->assertSame(
            ['category' => ['old' => null, 'new' => 'events']],
            $this->changes([], ['category' => 'events'])
        );
    }
}
