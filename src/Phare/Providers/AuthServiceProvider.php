<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Auth\AuthManager;
use Phare\Foundation\AbstractApplication as Application;

class AuthServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('auth.manager', function ($app) {
            return new AuthManager($app);
        });

        // Backwards compat: callers continue to do `app('auth')->user()` etc.
        // AuthManager forwards undeclared methods to the default guard.
        $app->singleton('auth', function ($app) {
            return $app->make('auth.manager');
        });
    }
}
