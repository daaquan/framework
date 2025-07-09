<?php

namespace Phare\Support\Facades;

/**
 * @method static void log(string $message, array $context = [])
 * @method static void logServiceProviderBooting()
 * @method static void logRouteStart(string $route)
 * @method static void logRouteEnd(string $route)
 * @method static void logMiddlewareStart(string $middleware)
 * @method static void logMiddlewareEnd(string $middleware)
 * @method static void logModelEvent(string $eventName, $modelName)
 * @method static void logCommandStart(string $commandName)
 * @method static void logCommandEnd(string $commandName)
 * @method static void logTerminate()
 *
 * @see \Phare\Debug\DebugLogger
 */
class DebugLogger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'debugLogger';
    }
}
