<?php

namespace Phare\Providers;

use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Support\ServiceProvider;

class ErrorHandlerProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        (new HandleExceptions())->register($app);
    }
}
