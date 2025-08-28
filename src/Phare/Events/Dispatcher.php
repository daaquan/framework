<?php

namespace Phare\Events;

use Phare\Contracts\Foundation\Application;
use Phare\Events\Contracts\Dispatcher as DispatcherContract;

class Dispatcher implements DispatcherContract
{
    protected Application $app;
    protected array $listeners = [];
    protected array $wildcards = [];
    protected array $queuedEvents = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function listen(string|array $events, \Closure|string $listener): void
    {
        $events = is_array($events) ? $events : [$events];

        foreach ($events as $event) {
            if (str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][] = $this->makeListener($listener);
            }
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) || $this->hasWildcardListeners($eventName);
    }

    public function dispatch(string|object $event, array $payload = [], bool $halt = false): ?array
    {
        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        $eventName = is_object($event) ? get_class($event) : $event;

        $listeners = $this->getListeners($eventName);
        $responses = [];

        foreach ($listeners as $listener) {
            $response = $this->callListener($listener, $event, $payload);

            if ($halt && $response !== null) {
                return [$response];
            }

            if ($response === false) {
                break;
            }

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        return $halt ? null : $responses;
    }

    public function until(string|object $event, array $payload = [])
    {
        $results = $this->dispatch($event, $payload, true);
        return $results ? $results[0] : null;
    }

    public function flush(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function forget(string $event): void
    {
        if (isset($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }

        foreach ($this->wildcards as $key => $listeners) {
            if ($this->eventMatches($key, $event)) {
                unset($this->wildcards[$key]);
            }
        }
    }

    public function forgetPushed(): void
    {
        $this->queuedEvents = [];
    }

    public function subscribe(object|string $subscriber): void
    {
        $subscriber = is_string($subscriber) ? $this->app->make($subscriber) : $subscriber;

        $events = $this->getSubscriberEvents($subscriber);

        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }

    protected function parseEventAndPayload($event, $payload): array
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], $event];
        }

        return [$event, $payload];
    }

    protected function getListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];

        $wildcardListeners = [];
        foreach ($this->wildcards as $key => $wildcardListeners) {
            if ($this->eventMatches($key, $eventName)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    protected function setupWildcardListen(string $event, $listener): void
    {
        $this->wildcards[$event][] = $this->makeListener($listener);
    }

    protected function hasWildcardListeners(string $eventName): bool
    {
        foreach ($this->wildcards as $key => $listeners) {
            if ($this->eventMatches($key, $eventName)) {
                return true;
            }
        }

        return false;
    }

    protected function eventMatches(string $pattern, string $event): bool
    {
        return fnmatch($pattern, $event);
    }

    protected function makeListener(\Closure|string $listener): \Closure
    {
        if (is_string($listener)) {
            return function (...$args) use ($listener) {
                return $this->createClassCallable($listener)(...$args);
            };
        }

        return $listener;
    }

    protected function createClassCallable(string $listener): \Closure
    {
        [$class, $method] = $this->parseClassCallable($listener);

        return function (...$args) use ($class, $method) {
            $callable = $this->app->make($class);
            return $callable->$method(...$args);
        };
    }

    protected function parseClassCallable(string $listener): array
    {
        if (str_contains($listener, '@')) {
            return explode('@', $listener, 2);
        }

        return [$listener, 'handle'];
    }

    protected function callListener(\Closure $listener, $event, array $payload)
    {
        if (is_object($event)) {
            return $listener($event);
        }

        return $listener($event, ...$payload);
    }

    protected function getSubscriberEvents(object $subscriber): array
    {
        if (method_exists($subscriber, 'subscribe')) {
            return $subscriber->subscribe();
        }

        $events = [];
        $methods = get_class_methods($subscriber);

        foreach ($methods as $method) {
            if (str_starts_with($method, 'on')) {
                $event = substr($method, 2);
                $events[$event][] = [$subscriber, $method];
            }
        }

        return $events;
    }
}