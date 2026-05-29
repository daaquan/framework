<?php

namespace Phare\Providers;

use Chronos\Chronos;
use Phare\Support\ServiceProvider;

class ChronosProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
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
