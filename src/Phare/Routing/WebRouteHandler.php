<?php

namespace Phare\Routing;

use Closure;
use Phalcon\Mvc\ControllerInterface;

/**
 * Owns the "register a single web route against the Phalcon Router" flow.
 *
 *  1. Bind the controller singleton so the dispatcher can find it.
 *  2. Add the route to the router with method + name.
 *  3. Apply the web group's middleware chain.
 *  4. If the route declares typed or URL params, register a
 *     dispatch:beforeExecuteRoute forward listener (delegated via the
 *     $registerForward callback supplied by the Kernel wiring).
 *
 * The handler depends only on closures so the Kernel can swap them in
 * tests without dragging in the real Application.
 */
class WebRouteHandler
{
    /**
     * @param Closure(array<int, string|callable>): void $applyMiddlewares
     * @param Closure(object, array, array): void $registerForward
     */
    public function __construct(
        private Closure $applyMiddlewares,
        private Closure $registerForward,
    ) {}

    /**
     * @param array<string, mixed> $routeData
     * @param array<string, mixed> $urlParams
     * @param array<int, string|callable> $webGroupMiddleware
     */
    public function handle(object $app, array $routeData, array $urlParams, array $webGroupMiddleware): void
    {
        $class = "{$routeData['namespace']}\\{$routeData['controller']}Controller";
        $app->singleton(ControllerInterface::class, $app->make($class));

        $route = $app['router']->add($routeData['path'], [
            'namespace' => $routeData['namespace'],
            'controller' => $routeData['controller'],
            'action' => $routeData['action'],
        ]);
        $route->via($routeData['method'])
            ->setName($routeData['name'] ?? '');

        ($this->applyMiddlewares)($webGroupMiddleware);

        if (empty($routeData['params']) && empty($urlParams)) {
            return;
        }

        ($this->registerForward)($app, $routeData, $urlParams);
    }
}
