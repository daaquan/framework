<?php

namespace Phare\Foundation\Bootstrap;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class RegisterProviders implements ServiceProviderInterface
{
    /**
     * Bootstrap the given application.
     */
    public function register(Application|DiInterface $app): void
    {
        $app->registerConfiguredProviders();
    }
}
