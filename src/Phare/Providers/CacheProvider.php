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
        $app->singleton('cache', function () {
            $manager = new CacheManager();

            return new CacheRepository($manager->adapter());
        });
    }
}
