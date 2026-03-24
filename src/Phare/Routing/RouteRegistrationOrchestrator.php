<?php

declare(strict_types=1);

namespace Phare\Routing;

class RouteRegistrationOrchestrator
{
    /**
     * Run route registration orchestration for current request.
     *
     * @param array<string, mixed> $allRoutes
     * @param array<string, string> $routeMiddlewareMap
     * @param callable(array<string, mixed>, string, string): ?array $matchParameterizedRoute
     * @param callable(array<string, mixed>): void $bindRouteParams
     * @param callable(array<string, mixed>, array<string, mixed>): void $mountWebRoute
     * @param callable(array<string, mixed>): void $mountMicroRoute
     * @param callable(array<int, string>): void $applyMiddlewares
     */
    public function register(
        array $allRoutes,
        string $mode,
        object $router,
        object $request,
        array $routeMiddlewareMap,
        callable $matchParameterizedRoute,
        callable $bindRouteParams,
        callable $mountWebRoute,
        callable $mountMicroRoute,
        callable $applyMiddlewares
    ): void {
        if ($mode === ApplicationModeResolver::MODE_WEB) {
            (new WebRouterHydrator())->hydrate($router, $allRoutes);
        }

        $uri = $request->getURI(true) ?: '/';
        $method = $request->getMethod();

        [$routeData, $routeParams] = (new RouteSelectionResolver())->resolve(
            $allRoutes,
            $uri,
            $method,
            $matchParameterizedRoute
        );

        $bindRouteParams($routeParams);

        (new RouteMountDispatcher())->dispatch(
            $mode,
            fn () => $mountWebRoute($routeData, $routeParams),
            fn () => $mountMicroRoute($routeData)
        );

        $routeMiddlewares = (new RouteMiddlewareResolver())->resolve(
            $routeData['middleware'] ?? [],
            $routeMiddlewareMap
        );

        $applyMiddlewares($routeMiddlewares);
    }
}
