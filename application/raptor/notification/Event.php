<?php

namespace Raptor\Notification;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Class Event
 *
 * PSR-14 суурь event класс.
 * Бүх event-үүд энэ классыг өргөтгөнө.
 *
 * @package Raptor\Notification
 */
class Event implements StoppableEventInterface
{
    private bool $stopped = false;

    /** @var string Үйлдэл хийсэн хэрэглэгчийн нэр */
    public readonly string $user;

    public function __construct(string $user = '')
    {
        $this->user = $user;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }
}
