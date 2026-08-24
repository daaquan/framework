<?php

namespace Phare\Foundation\Bootstrap;

use Phare\Foundation\AbstractApplication as Application;

class RegisterProviders
{
    /**
     * Bootstrap the given application.
     */
    public function register(Application $app): void
    {
        $app->registerConfiguredProviders();
    }
}
