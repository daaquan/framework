<?php

namespace Phare\Eloquent\Concerns;

use InvalidArgumentException;
use Phalcon\Di\Di;
use Phare\Events\Contracts\Dispatcher;

trait HasEvents
{
    protected static ?Dispatcher $eventDispatcher = null;

    /**
     * @var array<class-string, array<int, object|string>>
     */
    protected static array $observers = [];

    /**
     * @var array<class-string, int>
     */
    protected static array $eventsSuppressed = [];

    /**
     * @var array<class-string, int>
     */
    protected static array $touchSuppressed = [];

    public static function setEventDispatcher(?Dispatcher $dispatcher): void
    {
        static::$eventDispatcher = $dispatcher;
    }

    public static function unsetEventDispatcher(): void
    {
        static::$eventDispatcher = null;
    }

    public static function getEventDispatcher(): ?Dispatcher
    {
        if (static::$eventDispatcher !== null) {
            return static::$eventDispatcher;
        }

        $di = Di::getDefault();
        if ($di === null || !method_exists($di, 'has') || !$di->has('events')) {
            return null;
        }

        $dispatcher = $di->getShared('events');

        return $dispatcher instanceof Dispatcher ? $dispatcher : null;
    }

    public static function observe(object|array|string $classes): void
    {
        foreach ((array) $classes as $class) {
            foreach (static::$observers[static::class] ?? [] as $registered) {
                if ($registered === $class) {
                    continue 2;
                }
            }

            static::$observers[static::class][] = $class;
        }
    }

    public static function withoutEvents(callable $callback): mixed
    {
        static::$eventsSuppressed[static::class] = (static::$eventsSuppressed[static::class] ?? 0) + 1;

        try {
            return $callback();
        } finally {
            static::decrementScopedCounter(static::$eventsSuppressed, static::class);
        }
    }

    public static function withoutTouching(callable $callback): mixed
    {
        static::$touchSuppressed[static::class] = (static::$touchSuppressed[static::class] ?? 0) + 1;

        try {
            return $callback();
        } finally {
            static::decrementScopedCounter(static::$touchSuppressed, static::class);
        }
    }

    public static function isIgnoringEvents(?string $class = null): bool
    {
        return static::isClassScoped(static::$eventsSuppressed, $class ?? static::class);
    }

    public static function isIgnoringTouch(?string $class = null): bool
    {
        return static::isClassScoped(static::$touchSuppressed, $class ?? static::class);
    }

    protected static function registerModelEvent(string $event, \Closure|array|string $callback): void
    {
        $dispatcher = static::getEventDispatcher();
        if ($dispatcher === null) {
            return;
        }

        $dispatcher->listen(static::modelEventName($event), $callback);
    }

    protected static function modelEventName(string $event): string
    {
        return 'eloquent.' . $event . ': ' . static::class;
    }

    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (static::isIgnoringEvents(static::class)) {
            return true;
        }

        $method = $halt ? 'until' : 'dispatch';

        $observerResult = $this->filterModelEventResults($this->fireObserverEvent($event, $halt));
        if ($observerResult === false) {
            return false;
        }

        $customResult = $this->filterModelEventResults($this->fireCustomModelEvent($event, $method));
        if ($customResult === false) {
            return false;
        }

        $dispatcher = static::getEventDispatcher();
        if ($dispatcher === null) {
            return $this->eventResult($observerResult, $customResult, $halt);
        }

        $dispatcherResult = $this->filterModelEventResults(
            $dispatcher->{$method}(static::modelEventName($event), $this)
        );

        if ($dispatcherResult === false) {
            return false;
        }

        return $this->eventResult($observerResult, $customResult, $dispatcherResult, $halt);
    }

    protected function fireCustomModelEvent(string $event, string $method): mixed
    {
        if (!property_exists($this, 'dispatchesEvents') || !isset($this->dispatchesEvents[$event])) {
            return null;
        }

        $dispatcher = static::getEventDispatcher();
        if ($dispatcher === null) {
            return null;
        }

        $eventClass = $this->dispatchesEvents[$event];

        return $dispatcher->{$method}(new $eventClass($this));
    }

    protected function fireObserverEvent(string $event, bool $halt): mixed
    {
        $responses = [];

        foreach (static::$observers[static::class] ?? [] as $observer) {
            $instance = $this->resolveObserver($observer);

            if (!method_exists($instance, $event)) {
                continue;
            }

            $response = $instance->{$event}($this);

            if ($halt && $response !== null) {
                return $response;
            }

            if ($response === false) {
                return false;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    protected function filterModelEventResults(mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        return array_values(array_filter($result, static fn (mixed $response): bool => $response !== null));
    }

    protected function resolveObserver(object|string $observer): object
    {
        if (is_object($observer)) {
            return $observer;
        }

        if (!class_exists($observer)) {
            throw new InvalidArgumentException('Unable to find observer: ' . $observer);
        }

        $di = Di::getDefault();

        if ($di !== null && method_exists($di, 'has') && method_exists($di, 'get') && $di->has($observer)) {
            return $di->get($observer);
        }

        return new $observer();
    }

    protected static function decrementScopedCounter(array &$registry, string $class): void
    {
        if (!isset($registry[$class])) {
            return;
        }

        $registry[$class]--;

        if ($registry[$class] <= 0) {
            unset($registry[$class]);
        }
    }

    protected static function isClassScoped(array $registry, string $class): bool
    {
        foreach ($registry as $registeredClass => $depth) {
            if ($depth <= 0) {
                continue;
            }

            if ($class === $registeredClass || is_subclass_of($class, $registeredClass)) {
                return true;
            }
        }

        return false;
    }

    protected function eventResult(mixed ...$arguments): mixed
    {
        $halt = array_pop($arguments);
        $results = [];

        foreach ($arguments as $result) {
            if ($result === null || $result === [] || $result === true) {
                continue;
            }

            $results[] = $result;
        }

        if ($halt) {
            return $results[0] ?? true;
        }

        if ($results === []) {
            return [];
        }

        return array_merge(
            ...array_map(
                static fn (mixed $result): array => is_array($result) ? $result : [$result],
                $results
            )
        );
    }

    public static function retrieved(\Closure|array|string $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }

    public static function creating(\Closure|array|string $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    public static function created(\Closure|array|string $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    public static function updating(\Closure|array|string $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    public static function updated(\Closure|array|string $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    public static function saving(\Closure|array|string $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    public static function saved(\Closure|array|string $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    public static function deleting(\Closure|array|string $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    public static function deleted(\Closure|array|string $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    public static function restoring(\Closure|array|string $callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    public static function restored(\Closure|array|string $callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    public static function softDeleted(\Closure|array|string $callback): void
    {
        static::registerModelEvent('trashed', $callback);
    }

    public static function forceDeleting(\Closure|array|string $callback): void
    {
        static::registerModelEvent('forceDeleting', $callback);
    }

    public static function forceDeleted(\Closure|array|string $callback): void
    {
        static::registerModelEvent('forceDeleted', $callback);
    }

    public static function replicating(\Closure|array|string $callback): void
    {
        static::registerModelEvent('replicating', $callback);
    }

    public function saveQuietly(array $options = []): bool
    {
        return static::withoutEvents(fn (): bool => $this->save());
    }

    public function deleteQuietly(): bool
    {
        return static::withoutEvents(fn (): bool => $this->delete());
    }
}
