<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Cache\CacheManager;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Foundation\Cache as CacheRepository;

class CacheProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('cache.manager', function ($app) {
            return new CacheManager($app);
        });

        $app->singleton('cache', function ($app) {
            return new CacheRepository($app->make('cache.manager')->store());
        });
    }
}
