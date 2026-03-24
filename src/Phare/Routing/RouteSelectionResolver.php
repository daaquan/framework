<?php

declare(strict_types=1);

namespace Phare\Routing;

use Phalcon\Mvc\Router\Exception as RouteException;

class RouteSelectionResolver
{
    /**
     * Resolve route data by direct map lookup, then parameterized matching.
     *
     * @param array<string, mixed> $allRoutes
     * @param callable(array<string, mixed>, string, string): ?array $matchParameterizedRoute
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function resolve(
        array $allRoutes,
        string $uri,
        string $method,
        callable $matchParameterizedRoute
    ): array {
        if (isset($allRoutes[$uri][$method])) {
            return [$allRoutes[$uri][$method], []];
        }

        $matched = $matchParameterizedRoute($allRoutes, $uri, $method);
        if ($matched === null) {
            throw new RouteException("Route \"$method $uri\" not found.");
        }

        [$routeData, $routeParams] = $matched;

        return [$routeData, $routeParams ?? []];
    }
}
