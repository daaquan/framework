<?php

namespace Phare\Events\Contracts;

interface Dispatcher
{
    /**
     * Register an event listener with the dispatcher.
     */
    public function listen(string|array $events, \Closure|string $listener): void;

    /**
     * Determine if a given event has listeners.
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Fire an event and call the listeners.
     */
    public function dispatch(string|object $event, array $payload = [], bool $halt = false): ?array;

    /**
     * Fire an event until the first non-null response is returned.
     */
    public function until(string|object $event, array $payload = []);

    /**
     * Remove a set of listeners from the dispatcher.
     */
    public function flush(string $event): void;

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $event): void;

    /**
     * Forget all queued events.
     */
    public function forgetPushed(): void;

    /**
     * Register an event subscriber with the dispatcher.
     */
    public function subscribe(object|string $subscriber): void;
}
