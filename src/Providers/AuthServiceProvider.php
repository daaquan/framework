<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Auth\Manager as Auth;
use Phare\Foundation\AbstractApplication as Application;

class AuthServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('auth', function () use ($app) {
            return new Auth($app['session'], $app['config']['auth']);
        });
    }
}
