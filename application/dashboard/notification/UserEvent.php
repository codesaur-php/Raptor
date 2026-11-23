<?php

namespace Dashboard\Notification;

/**
 * Class UserEvent
 *
 * Хэрэглэгчтэй холбоотой event.
 * action: signup_request, approved, access_changed (DiscordListener::onUserEvent() зөвхөн эдгээрийг боловсруулна).
 * access_changed: details = ['roles_added' => string[], 'roles_removed' => string[]] - өндөр эрхтэй
 * дүрүүд, "alias_name (тайлбар)" хэлбэрээр.
 *
 * @package Dashboard\Notification
 */
class UserEvent extends Event
{
    public function __construct(
        public readonly string $action,
        public readonly string $username,
        public readonly string $email = '',
        public readonly array $details = []
    ) {
        parent::__construct();
    }
}
