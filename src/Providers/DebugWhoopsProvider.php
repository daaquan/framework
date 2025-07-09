<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\Micro as Application;

class DebugWhoopsProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if (!class_exists(\Whoops\Run::class) || !$app['config']->path('app.debug')) {
            return;
        }

        $whoops = new \Whoops\Run();
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
        $whoops->register();
        // round(microtime(true) - APP_START, 4).'ms'
    }
}
