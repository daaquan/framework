<?php

namespace Phare\Providers;

use Phare\Support\ServiceProvider;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class DebugWhoopsProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        if (!class_exists(Run::class) || !$app['config']->path('app.debug')) {
            return;
        }

        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
        // round(microtime(true) - APP_START, 4).'ms'
    }
}
