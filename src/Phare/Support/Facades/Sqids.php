<?php

namespace Phare\Support\Facades;

/**
 * @method static string encode(array $numbers)
 * @method static array decode(string $id)
 *
 * @see \Sqids\Sqids
 */
class Sqids extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'sqids';
    }
}
