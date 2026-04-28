<?php

namespace Raptor\Notification;

/**
 * Class DevRequestEvent
 *
 * Хөгжүүлэлтийн хүсэлтэй холбоотой event.
 *
 * @package Raptor\Notification
 */
class DevRequestEvent extends Event
{
    public function __construct(
        public readonly string $action,
        public readonly int $requestId,
        public readonly string $title,
        public readonly string $assignedTo = '',
        public readonly string $status = ''
    ) {
        parent::__construct();
    }
}
