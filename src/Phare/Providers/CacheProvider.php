<?php

namespace Phare\Providers;

use Phare\Cache\CacheManager;
use Phare\Foundation\Cache as CacheRepository;
use Phare\Support\ServiceProvider;

class CacheProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('cache.manager', function ($app) {
            return new CacheManager($app);
        });

        $app->singleton('cache', function ($app) {
            return new CacheRepository($app->make('cache.manager')->store());
        });
    }
}
