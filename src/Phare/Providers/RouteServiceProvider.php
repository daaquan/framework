<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Mvc\Router;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Routing\RouteLoader;

class RouteServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('router', fn () => new Router(false));

        // If the environment is not 'local' or 'testing', and routes are cached, simply return
        if (!$app->environment('local', 'testing') && $app->routesIsCached()) {
            return;
        }

        $routeCache = RouteLoader::create($app);
        if ($routeCache->isCacheUpToDate()) {
            return;
        }

        $routeCache->generateRoutesCacheFile();
    }
}
