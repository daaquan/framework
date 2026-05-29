<?php

namespace Phare\Providers;

use Phare\Http\Response;
use Phare\Support\ServiceProvider;

class ResponseProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('response', Response::class);
    }
}
