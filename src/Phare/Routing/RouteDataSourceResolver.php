<?php

declare(strict_types=1);

namespace Phare\Routing;

class RouteDataSourceResolver
{
    /**
     * Resolve route definitions from cache file or fallback loader.
     *
     * @param callable(string): array $fallbackLoader
     * @return array
     */
    public function resolve(string $cachedRoutesPath, callable $fallbackLoader): array
    {
        if (file_exists($cachedRoutesPath)) {
            return require $cachedRoutesPath;
        }

        $routes = $fallbackLoader($cachedRoutesPath);
        if (is_array($routes)) {
            return $routes;
        }

        if (file_exists($cachedRoutesPath)) {
            return require $cachedRoutesPath;
        }

        throw new \RuntimeException('Failed to resolve routes from cache or fallback loader.');
    }
}
