<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

use Raptor\Notification\DiscordListener;
use Raptor\Notification\DiscordNotifier;
use Raptor\Notification\ContentEvent;
use Raptor\Notification\UserEvent;
use Raptor\Notification\OrderEvent;
use Raptor\Notification\DevRequestEvent;

class DiscordListenerTest extends TestCase
{
    private DiscordNotifier $notifier;
    private DiscordListener $listener;

    protected function setUp(): void
    {
        // Mock notifier - бодит HTTP дуудлага хийхгүй
        $this->notifier = $this->getMockBuilder(DiscordNotifier::class)
            ->setConstructorArgs(['TestAdmin', 'example.com'])
            ->getMock();
        $this->listener = new DiscordListener($this->notifier);
    }

    // --- ContentEvent ---

    public function testOnContentEventCallsContentAction(): void
    {
        $this->notifier->expects($this->once())
            ->method('contentAction')
            ->with('news', 'delete', 'My Article', 42, 'Admin', []);

        $event = new ContentEvent('delete', 'news', 'My Article', 42, 'Admin');
        $this->listener->onContentEvent($event);
    }

    public function testOnContentEventWithUpdates(): void
    {
        $this->notifier->expects($this->once())
            ->method('contentAction')
            ->with('pages', 'update', 'Page', 1, 'Admin', ['title', 'slug']);

        $event = new ContentEvent('update', 'pages', 'Page', 1, 'Admin', ['title', 'slug']);
        $this->listener->onContentEvent($event);
    }

    public function testOnContentEventFallbackAdminName(): void
    {
        $this->notifier->expects($this->once())
            ->method('contentAction')
            ->with('news', 'insert', 'Test', null, 'TestAdmin', []);

        $event = new ContentEvent('insert', 'news', 'Test');
        $this->listener->onContentEvent($event);
    }

    // --- UserEvent ---

    public function testOnUserEventSignupRequest(): void
    {
        $this->notifier->expects($this->once())
            ->method('userSignupRequest')
            ->with('john', 'john@test.com');

        $event = new UserEvent('signup_request', 'john', 'john@test.com');
        $this->listener->onUserEvent($event);
    }

    public function testOnUserEventApproved(): void
    {
        $this->notifier->expects($this->once())
            ->method('userApproved')
            ->with('jane', 'jane@test.com', 'TestAdmin');

        $event = new UserEvent('approved', 'jane', 'jane@test.com');
        $this->listener->onUserEvent($event);
    }

    public function testOnUserEventUnknownAction(): void
    {
        $this->notifier->expects($this->never())->method('userSignupRequest');
        $this->notifier->expects($this->never())->method('userApproved');

        $event = new UserEvent('unknown', 'test');
        $this->listener->onUserEvent($event);
    }

    // --- OrderEvent ---

    public function testOnOrderEventNew(): void
    {
        $this->notifier->expects($this->once())
            ->method('newOrder')
            ->with(100, 'Customer', 'c@t.com', 'Product', 2, '+976');

        $event = new OrderEvent('new', 100, 'Customer', 'c@t.com', '+976', 'Product', 2);
        $this->listener->onOrderEvent($event);
    }

    public function testOnOrderEventStatusChanged(): void
    {
        $this->notifier->expects($this->once())
            ->method('orderStatusChanged')
            ->with(50, 'Buyer', 'new', 'confirmed', 'TestAdmin');

        $event = new OrderEvent('status_changed', 50, 'Buyer', '', '', '', 0, 'new', 'confirmed');
        $this->listener->onOrderEvent($event);
    }

    // --- DevRequestEvent ---

    public function testOnDevRequestEventNew(): void
    {
        $this->notifier->expects($this->once())
            ->method('newDevRequest')
            ->with(7, 'Fix bug', 'TestAdmin', 'Dev1');

        $event = new DevRequestEvent('new', 7, 'Fix bug', 'Dev1');
        $this->listener->onDevRequestEvent($event);
    }

    public function testOnDevRequestEventUpdated(): void
    {
        $this->notifier->expects($this->once())
            ->method('devRequestUpdated')
            ->with(7, 'Fix bug', 'TestAdmin', 'resolved');

        $event = new DevRequestEvent('updated', 7, 'Fix bug', '', 'resolved');
        $this->listener->onDevRequestEvent($event);
    }
}
