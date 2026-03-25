<?php

declare(strict_types=1);

namespace Phare\Routing;

class WebRouterHydrator
{
    /**
     * Hydrate router with all cached route definitions for web application mode.
     *
     * @param object $router Router object exposing add(...): object and route methods via()/setName().
     * @param array<string, mixed> $allRoutes
     */
    public function hydrate(object $router, array $allRoutes): void
    {
        foreach ($allRoutes as $routes) {
            if (!is_array($routes)) {
                continue;
            }

            foreach ($routes as $routeData) {
                if (!is_array($routeData)) {
                    continue;
                }

                $route = $router->add($routeData['path'], [
                    'namespace' => $routeData['namespace'],
                    'controller' => $routeData['controller'],
                    'action' => $routeData['action'],
                ]);

                $route->via($routeData['method'])
                    ->setName($routeData['name'] ?? '');
            }
        }
    }
}
