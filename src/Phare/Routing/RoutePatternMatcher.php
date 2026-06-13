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
        foreach ($this->sortBySpecificity($allRoutes) as $pattern => $methods) {
            if (!is_array($methods) || !isset($methods[$method])) {
                continue;
            }

            if (!str_contains((string)$pattern, '{')) {
                continue;
            }

            $paramNames = [];
            $regex = preg_replace_callback('/\{([\w\-]+)(?:<([^>]+)>)?\}/', function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                $constraint = $matches[2] ?? '[\w\-]+';

                return "($constraint)";
            }, (string)$pattern);

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

    /**
     * Order route patterns most-specific-first so that static segments win
     * over parameter segments (e.g. "/{path}/create" before "/{path}/{id}").
     *
     * Sorting is stable in PHP, so equally specific routes keep their original
     * registration order.
     *
     * @param array<string, mixed> $allRoutes
     * @return array<string, mixed>
     */
    private function sortBySpecificity(array $allRoutes): array
    {
        uksort($allRoutes, function ($a, $b): int {
            return $this->specificity((string)$b) <=> $this->specificity((string)$a);
        });

        return $allRoutes;
    }

    /**
     * Higher score = more specific. More literal/static segments rank higher;
     * fewer parameters rank higher.
     */
    private function specificity(string $pattern): int
    {
        $segments = array_filter(explode('/', $pattern), static fn ($s) => $s !== '');

        $static = 0;
        $params = 0;

        foreach ($segments as $segment) {
            if (str_contains($segment, '{')) {
                $params++;
            } else {
                $static++;
            }
        }

        // Weight static segments heavily, then penalize parameter count.
        return ($static * 100) - $params;
    }
}
