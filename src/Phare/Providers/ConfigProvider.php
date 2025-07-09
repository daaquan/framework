<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Config\Repository;
use Phare\Foundation\AbstractApplication as Application;

class ConfigProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('config', Repository::class);
    }
}
