<?php

namespace Phare\Providers;

use Phare\Debug\DebugLogger;
use Phare\Support\ServiceProvider;

class DebugLoggerProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('debugLogger', function () use ($app) {
            return new DebugLogger($app);
        });
    }
}
