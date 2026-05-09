<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Hashing\HashManager;

class HashServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('hash.manager', function ($app) {
            $default = $app['config']?->path('hashing.driver') ?? 'bcrypt';

            return new HashManager($default);
        });

        // Backwards compat: callers continue to do `app('hash')->make($value)` etc.
        // HashManager forwards undeclared methods to the default driver.
        $app->singleton('hash', function ($app) {
            return $app->make('hash.manager');
        });
    }
}
