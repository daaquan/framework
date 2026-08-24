<?php

namespace Phare\Support\Facades;

use Phare\Session\SessionManager;

/**
 * @method static bool start()
 * @method static SessionManager regenerateId(bool $deleteOldSession = true)
 * @method static mixed get(string $key, mixed $default = null, bool $remove = false)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void remove(string $key)
 * @method static bool exists()
 * @method static int status()
 * @method static void destroy()
 * @method static string getId()
 * @method static string getName()
 * @method static array getOptions()
 * @method static \SessionHandlerInterface getAdapter()
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
