<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Debug\DebugLogger;

class DebugLoggerProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('debugLogger', function () use ($app) {
            return new DebugLogger($app);
        });
    }
}
