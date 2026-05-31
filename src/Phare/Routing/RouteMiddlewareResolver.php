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
            // Support parameterised aliases such as "throttle:5,1": split the
            // base alias from its parameters, resolve the base, then re-attach
            // the parameters to the concrete class (mirrors the HTTP kernel's
            // resolveRouteMiddlewareAlias()).
            [$name, $parameters] = array_pad(explode(':', $alias, 2), 2, null);

            $middleware = $routeMiddleware[$name] ?? null;
            if ($middleware === null) {
                throw new \RuntimeException("Middleware alias \"{$name}\" not found.");
            }

            $resolved[] = $parameters !== null && $parameters !== ''
                ? $middleware . ':' . $parameters
                : $middleware;
        }

        return $resolved;
    }
}
