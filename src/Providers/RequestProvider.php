<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Mvc\Url as UrlResolver;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Http\Request;

class RequestProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('url', new UrlResolver());

        $app->singleton('request', Request::class);
    }
}
