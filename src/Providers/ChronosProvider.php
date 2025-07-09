<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class ChronosProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if (!extension_loaded('chronos')) {
            throw new \RuntimeException('Chronos extension is not loaded.');
        }

        if ($timezone = $app['config']->path('app.timezone')) {
            ini_set('date.timezone', $timezone);
        }

        $app->singleton('now', fn () => \Chronos\Chronos::now());
    }
}
