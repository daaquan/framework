<?php

declare(strict_types=1);

namespace Phare\Routing;

class RouteParamsBinder
{
    /**
     * Bind matched route params into the application container when available.
     *
     * @param array<string, mixed> $routeParams
     * @param callable(string, callable): void $bindSingleton
     */
    public function bind(array $routeParams, callable $bindSingleton): void
    {
        if ($routeParams === []) {
            return;
        }

        $bindSingleton('routeParams', fn () => $routeParams);
    }
}
