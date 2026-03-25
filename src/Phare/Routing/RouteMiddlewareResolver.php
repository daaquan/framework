<?php

declare(strict_types=1);

namespace Phare\Routing;

class RouteMiddlewareResolver
{
    /**
     * Resolve route middleware aliases to concrete middleware classes.
     *
     * @param array<int, string> $aliases
     * @param array<string, string> $routeMiddleware
     * @return array<int, string>
     */
    public function resolve(array $aliases, array $routeMiddleware): array
    {
        $resolved = [];

        foreach ($aliases as $alias) {
            $middleware = $routeMiddleware[$alias] ?? null;
            if ($middleware === null) {
                throw new \RuntimeException("Middleware alias \"{$alias}\" not found.");
            }

            $resolved[] = $middleware;
        }

        return $resolved;
    }
}
