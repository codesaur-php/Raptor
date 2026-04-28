<?php

namespace Raptor\Notification;

/**
 * Class UserEvent
 *
 * Хэрэглэгчтэй холбоотой event.
 * signup_request, approved, deactivated гэх мэт.
 *
 * @package Raptor\Notification
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
