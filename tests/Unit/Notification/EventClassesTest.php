<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

use Raptor\Notification\Event;
use Raptor\Notification\ContentEvent;
use Raptor\Notification\UserEvent;
use Raptor\Notification\OrderEvent;
use Raptor\Notification\DevRequestEvent;

class EventClassesTest extends TestCase
{
    // --- Event (base) ---

    public function testEventDefaults(): void
    {
        $event = new Event();
        $this->assertEquals('', $event->user);
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testEventWithValues(): void
    {
        $event = new Event('Admin');
        $this->assertEquals('Admin', $event->user);
    }

    public function testEventStopPropagation(): void
    {
        $event = new Event();
        $this->assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    // --- ContentEvent ---

    public function testContentEvent(): void
    {
        $event = new ContentEvent('delete', 'news', 'My Article', 42, 'Admin', ['title']);
        $this->assertEquals('delete', $event->action);
        $this->assertEquals('news', $event->module);
        $this->assertEquals('My Article', $event->title);
        $this->assertEquals(42, $event->id);
        $this->assertEquals('Admin', $event->user);
        $this->assertEquals(['title'], $event->updates);
    }

    public function testContentEventDefaults(): void
    {
        $event = new ContentEvent('insert', 'text', 'keyword');
        $this->assertNull($event->id);
        $this->assertEquals('', $event->user);
        $this->assertEquals([], $event->updates);
    }

    public function testContentEventExtendsEvent(): void
    {
        $event = new ContentEvent('insert', 'news', 'Test');
        $this->assertInstanceOf(Event::class, $event);
    }

    // --- UserEvent ---

    public function testUserEvent(): void
    {
        $event = new UserEvent('signup_request', 'john', 'john@test.com');
        $this->assertEquals('signup_request', $event->action);
        $this->assertEquals('john', $event->username);
        $this->assertEquals('john@test.com', $event->email);
    }

    public function testUserEventDefaults(): void
    {
        $event = new UserEvent('approved', 'jane');
        $this->assertEquals('', $event->email);
    }

    // --- OrderEvent ---

    public function testOrderEvent(): void
    {
        $event = new OrderEvent(
            'new', 100, 'Customer', 'c@t.com', '+976', 'Product', 2
        );
        $this->assertEquals('new', $event->action);
        $this->assertEquals(100, $event->orderId);
        $this->assertEquals('Customer', $event->customer);
        $this->assertEquals('c@t.com', $event->email);
        $this->assertEquals('+976', $event->phone);
        $this->assertEquals('Product', $event->product);
        $this->assertEquals(2, $event->quantity);
    }

    public function testOrderEventStatusChanged(): void
    {
        $event = new OrderEvent(
            'status_changed', 50, 'Buyer', '', '', '', 0,
            'new', 'confirmed'
        );
        $this->assertEquals('new', $event->oldStatus);
        $this->assertEquals('confirmed', $event->newStatus);
    }

    // --- DevRequestEvent ---

    public function testDevRequestEvent(): void
    {
        $event = new DevRequestEvent('new', 7, 'Fix bug', 'Dev1');
        $this->assertEquals('new', $event->action);
        $this->assertEquals(7, $event->requestId);
        $this->assertEquals('Fix bug', $event->title);
        $this->assertEquals('Dev1', $event->assignedTo);
    }

    public function testDevRequestEventUpdated(): void
    {
        $event = new DevRequestEvent('updated', 7, 'Fix bug', '', 'resolved');
        $this->assertEquals('resolved', $event->status);
    }
}
