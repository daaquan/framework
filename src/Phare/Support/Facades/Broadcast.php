<?php

namespace Phare\Support\Facades;

use Phare\Support\Facade;

/**
 * @method static \Phare\Broadcasting\Broadcasters\Broadcaster driver(string $name = null)
 * @method static \Phare\Broadcasting\Broadcasters\Broadcaster connection(string $name = null)
 * @method static void channel(string $channel, callable $callback)
 * @method static void broadcast(array $channels, string $event, array $payload = [])
 * @method static void queue(mixed $event)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static \Phare\Broadcasting\BroadcastManager extend(string $driver, callable $callback)
 * @method static void purge(string $name = null)
 *
 * @see \Phare\Broadcasting\BroadcastManager
 */
class Broadcast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'broadcast';
    }
}
