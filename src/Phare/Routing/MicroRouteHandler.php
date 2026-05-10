<?php

namespace Phare\Routing;

use Closure;
use Phalcon\Mvc\Micro\Collection;

/**
 * Owns the "mount a single micro Collection" flow.
 *
 *  1. Build a Collection bound to the target controller.
 *  2. Apply prefix if the route declares one.
 *  3. Map the HTTP method to a route action.
 *  4. Apply the api group's middleware chain.
 *  5. Mount the collection onto the application.
 *
 * Like WebRouteHandler, the handler depends only on injected closures so
 * the Kernel can swap them in tests.
 */
class MicroRouteHandler
{
    /**
     * @param Closure(array<int, string|callable>): void $applyMiddlewares
     */
    public function __construct(private Closure $applyMiddlewares) {}

    /**
     * @param array<string, mixed> $routeData
     * @param array<int, string|callable> $apiGroupMiddleware
     */
    public function handle(object $app, array $routeData, array $apiGroupMiddleware): void
    {
        $route = new Collection();
        $route->setHandler("{$routeData['namespace']}\\{$routeData['controller']}Controller", true);

        if (isset($routeData['prefix'])) {
            $route->setPrefix($routeData['prefix']);
        }

        $method = $routeData['method'];
        $route->$method($routeData['path'], $routeData['action'], $routeData['name'] ?? '');

        ($this->applyMiddlewares)($apiGroupMiddleware);

        $app->mount($route);
    }
}
