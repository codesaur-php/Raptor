<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

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
            \dirname(__DIR__, 3) . '/application/raptor/ContainerMiddleware.php'
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
            \dirname(__DIR__, 3) . '/application/raptor/Controller.php'
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
            \dirname(__DIR__, 3) . '/application/raptor/notification/DiscordNotifier.php'
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
            \dirname(__DIR__, 3) . '/application/raptor/notification/DiscordNotifier.php'
        );
        $this->assertStringContainsString(
            'public readonly string $host',
            $source,
            'DiscordNotifier must have host property'
        );
    }

    /**
     * Controller dispatch() нь exception шидэхгүй (fail-safe).
     */
    public function testControllerDispatchCatchesExceptions(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/Controller.php'
        );
        \preg_match('/function\s+dispatch\s*\(object\s+\$event\).*?\{(.+?)\n    \}/s', $source, $m);
        $this->assertNotEmpty($m, 'dispatch() method not found');
        $this->assertStringContainsString('catch', $m[1],
            'dispatch() must catch exceptions to prevent notification failures from breaking the app');
    }
}
