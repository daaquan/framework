<?php

namespace Phare\Providers;

use Phare\Auth\AuthManager;
use Phare\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
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
