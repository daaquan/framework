<?php

namespace Phare\Providers;

use Phare\Log\LogManager;
use Phare\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('log', function () use ($app) {
            return new LogManager($app);
        });
    }
}
