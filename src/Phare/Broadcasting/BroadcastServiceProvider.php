<?php

namespace Phare\Broadcasting;

use Phare\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('broadcast.manager', function ($app) {
            return new BroadcastManager($app);
        });

        // Backwards compat: callers continue to do `app('broadcast')->event(...)`.
        // BroadcastManager forwards driver(?$name) for default and named drivers.
        $app->singleton('broadcast', function ($app) {
            return $app->make('broadcast.manager');
        });
    }
}
