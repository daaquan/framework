<?php

namespace Phare\Providers;

use Phare\Hashing\HashManager;
use Phare\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
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
