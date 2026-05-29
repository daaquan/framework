<?php

namespace Phare\Providers;

use Phalcon\Mvc\Router;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Routing\RouteLoader;
use Phare\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var Application $app */
        $app = $this->app;
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
