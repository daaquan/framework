<?php

namespace Phare\Support\Facades;

use Phare\Inertia\ResponseFactory;

/**
 * @method static \Phare\Inertia\Response render(string $component, array $props = [])
 * @method static void share(string|array $key, mixed $value = null)
 * @method static mixed getShared(?string $key = null)
 * @method static void version(string|\Closure|null $version)
 * @method static ?string getVersion()
 * @method static \Phare\Inertia\LazyProp lazy(callable $callback)
 * @method static \Phare\Inertia\OptionalProp optional(callable $callback)
 *
 * @see ResponseFactory
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'inertia';
    }
}
