<?php

declare(strict_types=1);

namespace Phare\Routing;

class RoutePatternMatcher
{
    /**
     * Match a URI against parameterized route patterns.
     *
     * @return array{0: array, 1: array<string, string>}|null
     */
    public function match(array $allRoutes, string $uri, string $method): ?array
    {
        foreach ($allRoutes as $pattern => $methods) {
            if (!is_array($methods) || !isset($methods[$method])) {
                continue;
            }

            if (!str_contains($pattern, '{')) {
                continue;
            }

            $paramNames = [];
            $regex = preg_replace_callback('/\{([\w\-]+)(?:<([^>]+)>)?\}/', function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                $constraint = $matches[2] ?? '[\w\-]+';

                return "($constraint)";
            }, $pattern);

            if ($regex === null) {
                continue;
            }

            $regex = '#^' . $regex . '$#';

            if (!preg_match($regex, $uri, $matches)) {
                continue;
            }

            array_shift($matches);
            $params = array_combine($paramNames, $matches);
            if ($params === false) {
                continue;
            }

            return [$methods[$method], $params];
        }

        return null;
    }
}
