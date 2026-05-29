<?php

namespace Phare\Support;

use Phare\Contracts\Foundation\Container;

abstract class ServiceProvider
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    abstract public function register(): void;

    public function boot(): void
    {
        // Default empty implementation
    }
}
