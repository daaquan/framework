<?php

namespace Phare\Events;

use Phare\Contracts\Foundation\Container;
use Phare\Events\Contracts\Dispatcher as DispatcherContract;

class Dispatcher implements DispatcherContract
{
    protected Container $app;

    protected array $listeners = [];

    protected array $wildcards = [];

    protected array $wildcardsCache = [];

    protected array $pushedEvents = [];

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function listen(string|array $events, \Closure|array|string $listener): void
    {
        $events = is_array($events) ? $events : [$events];

        foreach ($events as $event) {
            if (str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName])
            || isset($this->wildcards[$eventName])
            || $this->hasWildcardListeners($eventName);
    }

    public function hasWildcardListeners(string $eventName): bool
    {
        foreach ($this->wildcards as $key => $listeners) {
            if ($this->eventMatches($key, $eventName)) {
                return true;
            }
        }

        return false;
    }

    public function push(string $event, object|array $payload = []): void
    {
        $this->pushedEvents[$event] = true;

        $this->listen($event.'_pushed', function () use ($event, $payload) {
            $this->dispatch($event, $payload);
        });
    }

    public function dispatch(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        [$eventName, $eventPayload] = $this->parseEventAndPayload($event, $payload);

        $responses = [];

        foreach ($this->getListeners($eventName) as $listener) {
            $response = $listener($eventName, $eventPayload);

            if ($halt && $response !== null) {
                return $response;
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    public function until(string|object $event, mixed $payload = [])
    {
        return $this->dispatch($event, $payload, true);
    }

    public function flush(string $event): void
    {
        $this->dispatch($event.'_pushed');
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event], $this->wildcards[$event], $this->wildcardsCache[$event]);
    }

    public function forgetPushed(): void
    {
        foreach (array_keys($this->pushedEvents) as $event) {
            unset($this->listeners[$event.'_pushed']);
        }

        $this->pushedEvents = [];
    }

    public function subscribe(object|string $subscriber): void
    {
        $subscriber = $this->resolveSubscriber($subscriber);

        if (method_exists($subscriber, 'subscribe')) {
            $events = $this->resolveSubscriberEvents($subscriber);

            if (is_array($events)) {
                foreach ($events as $event => $listeners) {
                    foreach ((array) $listeners as $listener) {
                        if (is_string($listener) && method_exists($subscriber, $listener)) {
                            $this->listen($event, [get_class($subscriber), $listener]);

                            continue;
                        }

                        $this->listen($event, $listener);
                    }
                }

                return;
            }
        }

        foreach ($this->discoverSubscriberMethods($subscriber) as $event => $listener) {
            $this->listen($event, $listener);
        }
    }

    public function getListeners(string $eventName): array
    {
        $listeners = array_merge(
            $this->prepareListeners($eventName),
            $this->wildcardsCache[$eventName] ?? $this->getWildcardListeners($eventName)
        );

        if (class_exists($eventName)) {
            $listeners = $this->addInterfaceListeners($eventName, $listeners);
        }

        return $listeners;
    }

    protected function resolveSubscriber(object|string $subscriber): object
    {
        if (is_string($subscriber)) {
            return $this->app->make($subscriber);
        }

        return $subscriber;
    }

    protected function resolveSubscriberEvents(object $subscriber): mixed
    {
        try {
            return $subscriber->subscribe($this);
        } catch (\ArgumentCountError) {
            return $subscriber->subscribe();
        }
    }

    protected function parseEventAndPayload(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            return [get_class($event), [$event]];
        }

        return [$event, is_array($payload) ? $payload : [$payload]];
    }

    protected function prepareListeners(string $eventName): array
    {
        $listeners = [];

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listeners[] = $this->makeListener($listener);
        }

        return $listeners;
    }

    protected function getWildcardListeners(string $eventName): array
    {
        $wildcards = [];

        foreach ($this->wildcards as $key => $listeners) {
            if ($this->eventMatches($key, $eventName)) {
                foreach ($listeners as $listener) {
                    $wildcards[] = $this->makeListener($listener, true);
                }
            }
        }

        return $this->wildcardsCache[$eventName] = $wildcards;
    }

    protected function addInterfaceListeners(string $eventName, array $listeners): array
    {
        foreach (class_implements($eventName) ?: [] as $interface) {
            foreach ($this->prepareListeners($interface) as $listener) {
                $listeners[] = $listener;
            }
        }

        return $listeners;
    }

    protected function setupWildcardListen(string $event, \Closure|array|string $listener): void
    {
        $this->wildcards[$event][] = $listener;
        $this->wildcardsCache = [];
    }

    protected function eventMatches(string $pattern, string $event): bool
    {
        return fnmatch($pattern, $event);
    }

    protected function makeListener(\Closure|array|string $listener, bool $wildcard = false): \Closure
    {
        if (is_string($listener) || (is_array($listener) && isset($listener[0]) && is_string($listener[0]))) {
            return $this->createClassListener($listener, $wildcard);
        }

        return function (string $event, array $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return $listener($event, $payload);
            }

            return $listener(...array_values($payload));
        };
    }

    protected function createClassListener(array|string $listener, bool $wildcard = false): \Closure
    {
        return function (string $event, array $payload) use ($listener, $wildcard) {
            $callable = $this->createClassCallable($listener);

            if ($wildcard) {
                return $callable($event, $payload);
            }

            return $callable(...array_values($payload));
        };
    }

    protected function createClassCallable(array|string $listener): callable
    {
        [$class, $method] = is_array($listener)
            ? $listener
            : $this->parseClassCallable($listener);

        if (!method_exists($class, $method)) {
            $method = '__invoke';
        }

        return [$this->app->make($class), $method];
    }

    protected function parseClassCallable(string $listener): array
    {
        if (str_contains($listener, '@')) {
            return explode('@', $listener, 2);
        }

        return [$listener, 'handle'];
    }

    protected function discoverSubscriberMethods(object $subscriber): array
    {
        $events = [];

        foreach (get_class_methods($subscriber) as $method) {
            if (!str_starts_with($method, 'on')) {
                continue;
            }

            $event = substr($method, 2);
            if ($event === '') {
                continue;
            }

            $events[$event] = [$subscriber, $method];
        }

        return $events;
    }
}
