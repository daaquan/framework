<?php

namespace Phare\Providers;

use Phare\Contracts\Foundation\Application;
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

        // Whoops installs error/exception handlers globally. Under PHPUnit that
        // trips the "did not remove its own error handlers" risky warning, so
        // skip it in tests just like HandleExceptions does.
        if ($app instanceof Application && $app->runningUnitTests()) {
            return;
        }

        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
        // round(microtime(true) - APP_START, 4).'ms'
    }
}
