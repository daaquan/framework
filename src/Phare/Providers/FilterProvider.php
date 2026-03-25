<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Filter\Filter;
use Phare\Foundation\AbstractApplication as Application;

class FilterProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('filter', Filter::class);
    }
}
