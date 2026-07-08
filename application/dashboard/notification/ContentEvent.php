<?php

namespace Dashboard\Notification;

/**
 * Class ContentEvent
 *
 * Контент дээр хийсэн үйлдлийн event.
 * insert, update, delete, publish гэх мэт.
 *
 * @package Dashboard\Notification
 */
class ContentEvent extends Event
{
    public function __construct(
        public readonly string $action,
        public readonly string $module,
        public readonly string $title,
        public readonly ?int $id = null,
        string $user = '',
        public readonly array $updates = []
    ) {
        parent::__construct($user);
    }
}
