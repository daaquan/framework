<?php

namespace Phare\Foundation\Bootstrap;

use Phare\Foundation\AbstractApplication as Application;

class RegisterFacades
{
    /**
     * Bootstrap the given application.
     */
    public function register(Application $app): void
    {
        $app->registerConfiguredAliases();
    }
}
