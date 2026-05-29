<?php

namespace Phare\Providers;

use Phalcon\Filter\Filter;
use Phare\Support\ServiceProvider;

class FilterProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('filter', Filter::class);
    }
}
