<?php

namespace Phare\Support\Facades;

/**
 * @method static void listen(string|array $events, \Closure|array|string $listener)
 * @method static bool hasListeners(string $eventName)
 * @method static bool hasWildcardListeners(string $eventName)
 * @method static void push(string $event, object|array $payload = [])
 * @method static void flush(string $event)
 * @method static void subscribe(object|string $subscriber)
 * @method static mixed until(string|object $event, mixed $payload = [])
 * @method static mixed dispatch(string|object $event, mixed $payload = [], bool $halt = false)
 * @method static array getListeners(string $eventName)
 * @method static void forget(string $event)
 * @method static void forgetPushed()
 *
 * @see \Phare\Events\Dispatcher
 */
class Event extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'events';
    }
}
