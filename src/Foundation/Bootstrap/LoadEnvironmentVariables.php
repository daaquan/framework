<?php

namespace Phare\Foundation\Bootstrap;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class LoadEnvironmentVariables implements ServiceProviderInterface
{
    /**
     * Bootstrap the given application.
     */
    public function register(Application|DiInterface $app): void
    {
        (new \Phare\Bootstrap\LoadEnvironmentVariables())
            ->bootstrap($app);
    }
}
