<?php

namespace Tests\Unit\Template;

use Tests\Support\RaptorTestCase;

/**
 * Dashboard layout override тестүүд.
 *
 * Хамрах хүрээ:
 *  - Application::overrideDashboardLayout() - бүртгэл, fail-fast шалгалт
 *  - DashboardTrait::layout() - замын резолюц (default vs override)
 *
 * Application нь abstract тул named test double (TestableApplication)
 * ашиглаж, constructor-ийг тойрч (newInstanceWithoutConstructor)
 * instance үүсгэнэ - constructor нь бүх middleware/router бүртгэдэг тул
 * unit тестэд илүүц бөгөөд set_exception_handler зэрэг глобал
 * side-effect үүсгэнэ.
 */
class TestableApplication extends \Raptor\Application
{
}

class DashboardLayoutOverrideTest extends RaptorTestCase
{
    /**
     * Constructor-гүйгээр Application instance үүсгэх helper.
     */
    private function createApplication(): TestableApplication
    {
        return (new \ReflectionClass(TestableApplication::class))
            ->newInstanceWithoutConstructor();
    }

    /**
     * DashboardTrait ашигладаг controller үүсгэх helper.
     *
     * @param array $attributes Request attributes
     */
    private function createController(array $attributes = []): object
    {
        $request = $this->createMockRequest(\array_merge(
            ['pdo' => $this->createMock(\PDO::class)],
            $attributes
        ));

        return new class($request) extends \Raptor\Controller {
            use \Raptor\Template\DashboardTrait;
        };
    }

    /**
     * private layout() методыг reflection-оор дуудах helper.
     */
    private function callLayout(object $controller, string $filename): string
    {
        $method = new \ReflectionMethod($controller, 'layout');

        return $method->invoke($controller, $filename);
    }

    // ---------------------------------------------------------
    // Application::overrideDashboardLayout()
    // ---------------------------------------------------------

    public function testOverrideRegistersLayoutAndReturnsFluent(): void
    {
        $app = $this->createApplication();

        // Бодитоор байгаа файл хэрэгтэй - core-ийн өөрийн dashboard.html
        $custom = \dirname(__DIR__, 3)
            . '/application/raptor/template/dashboard.html';

        $result = $app->overrideDashboardLayout('dashboard.html', $custom);

        $this->assertSame($app, $result, 'Fluent chain-д $this буцаах ёстой');

        // private property нь эцэг Raptor\Application дээр зарлагдсан
        $layouts = new \ReflectionProperty(\Raptor\Application::class, 'layouts');
        $this->assertSame(
            ['dashboard.html' => $custom],
            $layouts->getValue($app)
        );
    }

    public function testOverrideThrowsWhenCustomFileMissing(): void
    {
        $app = $this->createApplication();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('template file not found');

        $app->overrideDashboardLayout('dashboard.html', '/no/such/file.html');
    }

    // ---------------------------------------------------------
    // DashboardTrait::layout()
    // ---------------------------------------------------------

    public function testLayoutReturnsCoreDefaultWithoutOverrides(): void
    {
        $controller = $this->createController();

        $path = $this->callLayout($controller, 'dashboard.html');

        $expected = \dirname(__DIR__, 3)
            . '/application/raptor/template/dashboard.html';
        $this->assertSame(
            \realpath($expected),
            \realpath($path),
            'Override бүртгээгүй үед core template-ийн зам буцах ёстой'
        );
    }

    public function testLayoutReturnsCustomPathWhenOverridden(): void
    {
        $controller = $this->createController([
            'dashboard_layouts' => [
                'dashboard.html' => '/custom/app/my-dashboard.html'
            ]
        ]);

        $this->assertSame(
            '/custom/app/my-dashboard.html',
            $this->callLayout($controller, 'dashboard.html')
        );
    }

    public function testLayoutFallsBackForFilenamesNotInMap(): void
    {
        $controller = $this->createController([
            'dashboard_layouts' => [
                'dashboard.html' => '/custom/app/my-dashboard.html'
            ]
        ]);

        $path = $this->callLayout($controller, 'modal-no-permission.html');

        $this->assertStringEndsWith(
            'modal-no-permission.html',
            $path
        );
        $this->assertStringNotContainsString(
            '/custom/app/',
            \strtr($path, '\\', '/'),
            'Map-д байхгүй файл core default руугаа унах ёстой'
        );
    }
}
