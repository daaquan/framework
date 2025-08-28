<?php

namespace Phare\Broadcasting;

use Phare\Providers\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('broadcast', function ($app) {
            return new BroadcastManager($app);
        });

        $this->app->alias('broadcast', BroadcastManager::class);
    }

    public function boot(): void
    {
        //
    }
}
