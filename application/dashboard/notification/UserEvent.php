<?php

namespace Dashboard\Notification;

/**
 * Class UserEvent
 *
 * Хэрэглэгчтэй холбоотой event.
 * action: signup_request, approved (DiscordListener::onUserEvent() зөвхөн эдгээрийг боловсруулна).
 *
 * @package Dashboard\Notification
 */
class UserEvent extends Event
{
    public function __construct(
        public readonly string $action,
        public readonly string $username,
        public readonly string $email = ''
    ) {
        parent::__construct();
    }
}
