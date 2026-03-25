<?php

namespace Phare\Eloquent\Concerns;

use Phalcon\Di\Di;
use Phare\Events\Contracts\Dispatcher;

trait HasEvents
{
    protected static ?Dispatcher $eventDispatcher = null;

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

    protected function fireModelEvent(string $event, bool $halt = false): mixed
    {
        $dispatcher = static::getEventDispatcher();
        if ($dispatcher === null) {
            return true;
        }

        $name = static::modelEventName($event);

        return $halt
            ? $dispatcher->until($name, $this)
            : $dispatcher->dispatch($name, $this);
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

    public static function forceDeleting(\Closure|array|string $callback): void
    {
        static::registerModelEvent('forceDeleting', $callback);
    }

    public static function forceDeleted(\Closure|array|string $callback): void
    {
        static::registerModelEvent('forceDeleted', $callback);
    }
}
