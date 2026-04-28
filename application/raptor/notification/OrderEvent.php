<?php

namespace Raptor\Notification;

/**
 * Class OrderEvent
 *
 * Захиалгатай холбоотой event.
 *
 * @package Raptor\Notification
 */
class OrderEvent extends Event
{
    public function __construct(
        public readonly string $action,
        public readonly int $orderId,
        public readonly string $customer,
        public readonly string $email = '',
        public readonly string $phone = '',
        public readonly string $product = '',
        public readonly int $quantity = 0,
        public readonly string $oldStatus = '',
        public readonly string $newStatus = ''
    ) {
        parent::__construct();
    }
}
