<?php

namespace Phare\Foundation\Bootstrap;

use Phare\Foundation\AbstractApplication as Application;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     */
    public function register(Application $app): void
    {
        (new \Phare\Bootstrap\LoadEnvironmentVariables())
            ->bootstrap($app);
    }
}
