<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ContainerMiddleware дээр events service бүртгэгдсэн эсэхийг шалгана.
 * Бүх controller-ууд dispatch() ашиглаж байгаа эсэхийг шалгана.
 */
class EventDispatchServiceTest extends TestCase
{
    private static string $containerSource;

    public static function setUpBeforeClass(): void
    {
        self::$containerSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/ContainerMiddleware.php'
        );
    }

    public function testEventsServiceRegistered(): void
    {
        $this->assertStringContainsString(
            "container->set('events'",
            self::$containerSource,
            'Events service must be registered in ContainerMiddleware'
        );
    }

    public function testEventsServiceUsesEventDispatcher(): void
    {
        $this->assertStringContainsString(
            'EventDispatcher',
            self::$containerSource,
            'Events service must use EventDispatcher'
        );
    }

    public function testEventsServiceUsesListenerProvider(): void
    {
        $this->assertStringContainsString(
            'ListenerProvider',
            self::$containerSource,
            'Events service must use ListenerProvider'
        );
    }

    public function testDiscordListenerRegistered(): void
    {
        $this->assertStringContainsString(
            'DiscordListener',
            self::$containerSource,
            'DiscordListener must be registered in events service'
        );
    }

    public function testContentEventListenerRegistered(): void
    {
        $this->assertStringContainsString(
            'ContentEvent::class',
            self::$containerSource,
            'ContentEvent listener must be registered'
        );
    }

    public function testUserEventListenerRegistered(): void
    {
        $this->assertStringContainsString(
            'UserEvent::class',
            self::$containerSource,
            'UserEvent listener must be registered'
        );
    }

    public function testOrderEventListenerRegistered(): void
    {
        $this->assertStringContainsString(
            'OrderEvent::class',
            self::$containerSource,
            'OrderEvent listener must be registered'
        );
    }

    public function testDevRequestEventListenerRegistered(): void
    {
        $this->assertStringContainsString(
            'DevRequestEvent::class',
            self::$containerSource,
            'DevRequestEvent listener must be registered'
        );
    }

    /**
     * Controller-д dispatch() method байх ёстой.
     */
    public function testControllerHasDispatchMethod(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/Controller.php'
        );
        $this->assertStringContainsString(
            'function dispatch(object $event)',
            $source,
            'Base Controller must have dispatch() method'
        );
    }

    public function testDiscordNotifierHasUserProperty(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/notification/DiscordNotifier.php'
        );
        $this->assertStringContainsString(
            'public readonly string $user',
            $source,
            'DiscordNotifier must have user property'
        );
    }

    public function testDiscordNotifierHasHostnameProperty(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/notification/DiscordNotifier.php'
        );
        $this->assertStringContainsString(
            'public readonly string $host',
            $source,
            'DiscordNotifier must have host property'
        );
    }

    /**
     * Action-аар салаалдаг event бүрийн хувьд: controller-оос dispatch хийсэн
     * action нь DiscordListener-ийн харгалзах method-ийн боловсруулдаг утга
     * байх ёстой. Listener нь танихгүй action-ийг чимээгүй алгасдаг тул
     * буруу утга (жишээ нь OrderEvent('insert')) мэдэгдлийг ямар ч
     * алдаагүйгээр алга болгоно. ContentEvent энд ороогүй - түүний
     * танихгүй action contentAction() fallback руу очдог.
     *
     * @return array<string, array{string, string}>
     */
    public static function actionRoutedEvents(): array
    {
        return [
            'OrderEvent' => ['OrderEvent', 'onOrderEvent'],
            'UserEvent' => ['UserEvent', 'onUserEvent'],
            'DevRequestEvent' => ['DevRequestEvent', 'onDevRequestEvent'],
        ];
    }

    #[DataProvider('actionRoutedEvents')]
    public function testDispatchedEventActionsAreHandledByListener(string $eventClass, string $listenerMethod): void
    {
        $listenerSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/notification/DiscordListener.php'
        );
        \preg_match('/function\s+' . $listenerMethod . '\s*\(.*?\n    \}/s', $listenerSource, $m);
        $this->assertNotEmpty($m, "$listenerMethod() method not found");
        \preg_match_all('/\$event->action\s*===\s*\'([^\']+)\'/', $m[0], $handled);
        $this->assertNotEmpty($handled[1], "$listenerMethod() must branch on \$event->action");

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            \dirname(__DIR__, 3) . '/application', \FilesystemIterator::SKIP_DOTS
        ));
        $pattern = '/new\s+\\\\?(?:Dashboard\\\\Notification\\\\)?' . $eventClass . '\s*\(\s*\'([^\']+)\'/';
        $found = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = \file_get_contents($file->getPathname());
            if (!\preg_match_all($pattern, $source, $dispatched)) {
                continue;
            }
            foreach ($dispatched[1] as $action) {
                $found++;
                $this->assertContains($action, $handled[1], \sprintf(
                    "%s dispatches %s('%s') but DiscordListener::%s() only handles: %s",
                    \basename($file->getPathname()), $eventClass, $action,
                    $listenerMethod, \implode(', ', $handled[1])
                ));
            }
        }
        $this->assertGreaterThan(0, $found, "No $eventClass dispatch found in application/");
    }

    /**
     * Controller dispatch() нь exception шидэхгүй (fail-safe).
     */
    public function testControllerDispatchCatchesExceptions(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/dashboard/Controller.php'
        );
        \preg_match('/function\s+dispatch\s*\(object\s+\$event\).*?\{(.+?)\n    \}/s', $source, $m);
        $this->assertNotEmpty($m, 'dispatch() method not found');
        $this->assertStringContainsString('catch', $m[1],
            'dispatch() must catch exceptions to prevent notification failures from breaking the app');
    }
}
