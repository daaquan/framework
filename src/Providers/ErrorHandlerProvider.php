<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Foundation\Bootstrap\HandleExceptions;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        (new HandleExceptions())->register($app);
    }
}
