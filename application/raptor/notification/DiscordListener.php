<?php

namespace Raptor\Notification;

/**
 * Class DiscordListener
 *
 * Event-үүдийг сонсож Discord webhook руу мэдэгдэл илгээх listener.
 *
 * @package Raptor\Notification
 */
class DiscordListener
{
    public function __construct(
        private readonly DiscordNotifier $notifier
    ) {
    }

    public function onContentEvent(ContentEvent $event): void
    {
        if ($event->module === 'message' && $event->action === 'new') {
            $this->notifier->newContactMessage(
                $event->user ?: $event->title,
                $event->updates['phone'] ?? '',
                $event->updates['email'] ?? '',
                $event->updates['message'] ?? ''
            );
            return;
        }

        if ($event->module === 'comment' && $event->action === 'insert') {
            $this->notifier->newComment(
                $event->user ?: $this->notifier->user,
                $event->title,
                $event->id ?? 0,
                $event->updates['news_title'] ?? ''
            );
            return;
        }

        if ($event->module === 'review' && $event->action === 'insert') {
            $this->notifier->newReview(
                $event->user ?: $this->notifier->user,
                (int) ($event->updates['rating'] ?? 0),
                (string) ($event->updates['comment'] ?? ''),
                $event->title,
                $event->id ?? 0
            );
            return;
        }

        $this->notifier->contentAction(
            $event->module,
            $event->action,
            $event->title,
            $event->id,
            $event->user ?: $this->notifier->user,
            $event->updates
        );
    }

    public function onUserEvent(UserEvent $event): void
    {
        if ($event->action === 'signup_request') {
            $this->notifier->userSignupRequest(
                $event->username, $event->email
            );
        } elseif ($event->action === 'approved') {
            $this->notifier->userApproved(
                $event->username, $event->email,
                $this->notifier->user
            );
        }
    }

    public function onOrderEvent(OrderEvent $event): void
    {
        if ($event->action === 'new') {
            $this->notifier->newOrder(
                $event->orderId, $event->customer, $event->email,
                $event->product, $event->quantity, $event->phone
            );
        } elseif ($event->action === 'status_changed') {
            $this->notifier->orderStatusChanged(
                $event->orderId, $event->customer,
                $event->oldStatus, $event->newStatus,
                $this->notifier->user
            );
        }
    }

    public function onDevRequestEvent(DevRequestEvent $event): void
    {
        $admin = $this->notifier->user;
        if ($event->action === 'new') {
            $this->notifier->newDevRequest(
                $event->requestId, $event->title,
                $admin, $event->assignedTo
            );
        } elseif ($event->action === 'updated') {
            $this->notifier->devRequestUpdated(
                $event->requestId, $event->title,
                $admin, $event->status
            );
        }
    }
}
