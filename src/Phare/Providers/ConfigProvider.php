<?php

namespace Phare\Providers;

use Phalcon\Config\Config;
use Phare\Support\ServiceProvider;

class ConfigProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('config', Config::class);
    }
}
