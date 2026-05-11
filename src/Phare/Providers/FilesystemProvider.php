<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Filesystem\FilesystemManager;
use Phare\Foundation\AbstractApplication as Application;

class FilesystemProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('filesystem.manager', function ($app) {
            return new FilesystemManager($app);
        });

        $app->singleton('filesystem', function ($app) {
            return $app->make('filesystem.manager')->disk();
        });
    }
}
