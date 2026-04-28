<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

use Raptor\Notification\ListenerProvider;
use Raptor\Notification\Event;
use Raptor\Notification\ContentEvent;
use Raptor\Notification\UserEvent;

class ListenerProviderTest extends TestCase
{
    public function testGetListenersForExactMatch(): void
    {
        $provider = new ListenerProvider();
        $called = false;

        $provider->listen(ContentEvent::class, function () use (&$called) {
            $called = true;
        });

        $event = new ContentEvent('insert', 'news', 'Test');
        foreach ($provider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        $this->assertTrue($called);
    }

    public function testGetListenersReturnsEmptyForUnregistered(): void
    {
        $provider = new ListenerProvider();
        $event = new ContentEvent('insert', 'news', 'Test');

        $listeners = \iterator_to_array($provider->getListenersForEvent($event));
        $this->assertEmpty($listeners);
    }

    public function testGetListenersSupportsInheritance(): void
    {
        $provider = new ListenerProvider();
        $parentCalled = false;
        $childCalled = false;

        $provider->listen(Event::class, function () use (&$parentCalled) {
            $parentCalled = true;
        });
        $provider->listen(ContentEvent::class, function () use (&$childCalled) {
            $childCalled = true;
        });

        $event = new ContentEvent('insert', 'news', 'Test');
        foreach ($provider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        $this->assertTrue($childCalled, 'Child class listener called');
        $this->assertTrue($parentCalled, 'Parent class listener called via inheritance');
    }

    public function testMultipleListenersForSameEvent(): void
    {
        $provider = new ListenerProvider();
        $count = 0;

        $provider->listen(UserEvent::class, function () use (&$count) { $count++; });
        $provider->listen(UserEvent::class, function () use (&$count) { $count++; });

        $event = new UserEvent('signup_request', 'test');
        foreach ($provider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        $this->assertEquals(2, $count);
    }

    public function testDifferentEventTypesIsolated(): void
    {
        $provider = new ListenerProvider();

        $provider->listen(ContentEvent::class, function () {});
        $provider->listen(UserEvent::class, function () {});

        $event = new ContentEvent('insert', 'news', 'Test');
        $listeners = \iterator_to_array($provider->getListenersForEvent($event));

        // ContentEvent listener + Event parent (no match since Event has no listener)
        // Only ContentEvent listener should match
        $this->assertCount(1, $listeners);
    }
}
