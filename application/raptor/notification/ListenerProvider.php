<?php

namespace Raptor\Notification;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ListenerProvider
 *
 * PSR-14 ListenerProvider.
 * Event класс дээр тулгуурлан тохирох listener-уудыг буцаана.
 *
 * @package Raptor\Notification
 */
class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    /**
     * Listener бүртгэх.
     *
     * @param string $eventClass Event классын нэр
     * @param callable $listener Listener callback
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = \get_class($event);

        // Яг тохирох listener-ууд
        yield from $this->listeners[$eventClass] ?? [];

        // Parent class-ын listener-ууд (inheritance support)
        foreach (\class_parents($event) as $parent) {
            yield from $this->listeners[$parent] ?? [];
        }
    }
}
