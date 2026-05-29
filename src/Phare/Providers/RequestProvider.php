<?php

namespace Phare\Providers;

use Phalcon\Mvc\Url as UrlResolver;
use Phare\Http\Request;
use Phare\Support\ServiceProvider;

class RequestProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('url', new UrlResolver());

        $app->singleton('request', Request::class);
    }
}
