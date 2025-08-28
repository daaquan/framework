<?php

namespace Phare\Support;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected Application|DiInterface $app;

    public function __construct(Application|DiInterface $app)
    {
        $this->app = $app;
    }

    abstract public function register(): void;

    public function boot(): void
    {
        // Default empty implementation
    }
}
