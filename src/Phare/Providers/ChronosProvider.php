<?php

namespace Phare\Providers;

use Chronos\Chronos;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class ChronosProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if ($timezone = $app['config']->path('app.timezone')) {
            ini_set('date.timezone', $timezone);
        }

        if (extension_loaded('chronos')) {
            $app->singleton('now', fn () => Chronos::now());

            return;
        }

        // Fallback for environments where the optional chronos extension is unavailable.
        $app->singleton('now', function () {
            $timezone = ini_get('date.timezone') ?: 'UTC';

            return new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        });
    }
}
