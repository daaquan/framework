<?php

namespace Phare\Support\Facades;

use Phare\Events\Dispatcher;

/**
 * @method static void listen(string|array $events, \Closure|array|string $listener)
 * @method static bool hasListeners(string $eventName)
 * @method static bool hasWildcardListeners(string $eventName)
 * @method static void push(string $event, object|array $payload = [])
 * @method static void flush(string $event)
 * @method static void subscribe(object|string $subscriber)
 * @method static mixed until(string|object $event, mixed $payload = [])
 * @method static mixed dispatch(string|object $event, mixed $payload = [], bool $halt = false)
 * @method static mixed dispatchIf(bool|\Closure $boolean, string|object $event, mixed $payload = [], bool $halt = false)
 * @method static mixed dispatchUnless(bool|\Closure $boolean, string|object $event, mixed $payload = [], bool $halt = false)
 * @method static mixed defer(callable $callback, ?array $events = null)
 * @method static \Phare\Events\Dispatcher setTransactionManagerResolver(?callable $resolver)
 * @method static array getListeners(string $eventName)
 * @method static array getRawListeners()
 * @method static void forget(string $event)
 * @method static void forgetPushed()
 *
 * @see Dispatcher
 */
class Event extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'events';
    }
}
