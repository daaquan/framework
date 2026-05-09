<?php

namespace Phare\Broadcasting;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class BroadcastServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
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
