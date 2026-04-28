<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

use Raptor\Notification\EventDispatcher;
use Raptor\Notification\ListenerProvider;
use Raptor\Notification\Event;
use Raptor\Notification\ContentEvent;
use Raptor\Notification\UserEvent;
use Raptor\Notification\OrderEvent;
use Raptor\Notification\DevRequestEvent;

class EventDispatcherTest extends TestCase
{
    private ListenerProvider $provider;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->provider = new ListenerProvider();
        $this->dispatcher = new EventDispatcher($this->provider);
    }

    public function testDispatchCallsListener(): void
    {
        $called = false;
        $this->provider->listen(ContentEvent::class, function (ContentEvent $e) use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new ContentEvent('insert', 'news', 'Test'));
        $this->assertTrue($called);
    }

    public function testDispatchPassesEventToListener(): void
    {
        $received = null;
        $this->provider->listen(ContentEvent::class, function (ContentEvent $e) use (&$received) {
            $received = $e;
        });

        $event = new ContentEvent('delete', 'pages', 'My Page', 42, 'Admin');
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
        $this->assertEquals('delete', $received->action);
        $this->assertEquals('pages', $received->module);
        $this->assertEquals('My Page', $received->title);
        $this->assertEquals(42, $received->id);
        $this->assertEquals('Admin', $received->user);
    }

    public function testDispatchCallsMultipleListeners(): void
    {
        $count = 0;
        $this->provider->listen(ContentEvent::class, function () use (&$count) { $count++; });
        $this->provider->listen(ContentEvent::class, function () use (&$count) { $count++; });
        $this->provider->listen(ContentEvent::class, function () use (&$count) { $count++; });

        $this->dispatcher->dispatch(new ContentEvent('insert', 'news', 'Test'));
        $this->assertEquals(3, $count);
    }

    public function testDispatchReturnsEvent(): void
    {
        $event = new ContentEvent('update', 'text', 'Keyword');
        $result = $this->dispatcher->dispatch($event);
        $this->assertSame($event, $result);
    }

    public function testStopPropagation(): void
    {
        $calls = [];
        $this->provider->listen(ContentEvent::class, function (ContentEvent $e) use (&$calls) {
            $calls[] = 'first';
            $e->stopPropagation();
        });
        $this->provider->listen(ContentEvent::class, function () use (&$calls) {
            $calls[] = 'second';
        });

        $event = new ContentEvent('delete', 'news', 'Test');
        $this->dispatcher->dispatch($event);

        $this->assertEquals(['first'], $calls);
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testNoListenersDoesNotThrow(): void
    {
        $event = new ContentEvent('insert', 'news', 'Test');
        $result = $this->dispatcher->dispatch($event);
        $this->assertSame($event, $result);
    }

    public function testParentClassListenerReceivesChildEvent(): void
    {
        $received = null;
        // Event (parent) listener
        $this->provider->listen(Event::class, function (Event $e) use (&$received) {
            $received = $e;
        });

        $event = new ContentEvent('insert', 'news', 'Test', null, 'Admin');
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
        $this->assertEquals('Admin', $received->user);
    }

    public function testDifferentEventTypesDoNotCross(): void
    {
        $contentCalled = false;
        $userCalled = false;

        $this->provider->listen(ContentEvent::class, function () use (&$contentCalled) {
            $contentCalled = true;
        });
        $this->provider->listen(UserEvent::class, function () use (&$userCalled) {
            $userCalled = true;
        });

        $this->dispatcher->dispatch(new ContentEvent('insert', 'news', 'Test'));

        $this->assertTrue($contentCalled);
        $this->assertFalse($userCalled);
    }
}
