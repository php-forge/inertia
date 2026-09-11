<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Fixture;

use PHPForge\Inertia\Event\ProtocolResultCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Event dispatcher that records every dispatched event and forwards protocol results to the configured listeners.
 *
 * Lets a test observe real dispatch order and count without a mock.
 */
final class CollectingEventDispatcherStub implements EventDispatcherInterface
{
    /**
     * @var list<object> Events dispatched so far, in dispatch order.
     */
    public array $events = [];

    /**
     * @param list<callable(ProtocolResultCreated): void> $listeners Listeners invoked for every protocol result.
     */
    public function __construct(private readonly array $listeners = []) {}

    /**
     * Records the event and forwards it to every listener.
     *
     * @param object $event Event to dispatch.
     *
     * @return object The dispatched event.
     */
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        if ($event instanceof ProtocolResultCreated) {
            foreach ($this->listeners as $listener) {
                $listener($event);
            }
        }

        return $event;
    }
}
