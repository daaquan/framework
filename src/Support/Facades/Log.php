<?php

namespace Phare\Support\Facades;

/**
 * @method static void emergency($message, array $context = [])
 * @method static void critical($message, array $context = [])
 * @method static void error($message, array $context = [])
 * @method static void alert($message, array $context = [])
 * @method static void info($message, array $context = [])
 * @method static void warning($message, array $context = [])
 * @method static void debug($message, array $context = [])
 * @method static void write($level, $message, array $context = [])
 * @method static void log($level, $message, array $context = [])
 * @method static array getChannels()
 */
class Log extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'log';
    }
}
