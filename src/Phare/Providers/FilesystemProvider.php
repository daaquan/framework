<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phare\Filesystem\FilesystemManager;
use Phare\Support\ServiceProvider;

class FilesystemProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('filesystem.manager', function ($app) {
            return new FilesystemManager($app);
        });

        $app->singleton('filesystem', function ($app) {
            return $app->make('filesystem.manager')->disk();
        });
    }
}
