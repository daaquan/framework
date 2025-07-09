<?php

namespace Phare\Support\Facades;

use Phalcon\Session\ManagerInterface;
use Phare\Session\SessionManager;

/**
 * @method static void start()
 * @method static ManagerInterface regenerateId(bool $deleteOldSession = true)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value)
 * @method static void add(string $key, mixed $value)
 * @method static void clear()
 * @method static void forget(string $key)
 * @method static void replace(array $attributes)
 *
 * @see SessionManager
 */
class Session extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
