<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\Micro as Application;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class DebugWhoopsProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if (!class_exists(Run::class) || !$app['config']->path('app.debug')) {
            return;
        }

        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
        // round(microtime(true) - APP_START, 4).'ms'
    }
}
