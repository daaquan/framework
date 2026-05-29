<?php

namespace Phare\Providers;

use Phare\Support\ServiceProvider;
use Sqids\Sqids;

class SqidsProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        if (!extension_loaded('sqids')) {
            return;
        }

        $app->singleton('sqids', function () {
            return new Sqids(Sqids::DEFAULT_ALPHABET, 10);
        });
    }
}
