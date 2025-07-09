<?php

namespace Phare\Routing;

use Phare\Foundation\AbstractApplication as Application;

abstract class RouteLoader
{
    protected string $path;

    protected string|array|null $route = null;

    protected static ?RouteLoader $instance = null;

    protected function __construct(protected Application $app, protected array $routePaths = [])
    {
        $app->singleton('router', fn () => new \Phalcon\Mvc\Router(false));
    }

    public static function create(Application $app): RouteLoader
    {
        return self::$instance ??= self::createFromControllers($app);
    }

    private static function createFromControllers(Application $app): RouteLoader
    {
        return new ControllerRouteLoader($app, [base_path('app/Http/Controllers')]);
    }

    private static function createFromRouteFiles(Application $app): RouteLoader
    {
        return new FileRouteLoader($app, [base_path('routes')]);
    }

    protected function extractRoutes($router)
    {
        $routes = [];
        foreach ($router->getRoutes() as $route) {
            $prefix = $route['prefix'] ?? '';
            unset($route['prefix']);

            $uri = normalize_uri($prefix, $route['path']);
            $route['path'] = $uri;

            $route['name'] ??= 'generated-' . str_random(10);

            try {
                $controller = new \ReflectionClass($route['controller']);
            } catch (\ReflectionException $e) {
                // If the controller class does not exist, skip it
                continue;
            }

            $route['namespace'] = $controller->getNamespaceName();
            $route['controller'] = substr($controller->getShortName(), 0, -10);

            $routes[$uri][$route['method']] = $route;
        }

        return $routes;
    }

    /**
     * Determine if the routes cache is up to date with the routes files.
     */
    public function isCacheUpToDate(): bool
    {
        if (!$this->app->routesIsCached()) {
            return false;
        }

        $cachedRoutes = require $this->app->routesCachePath();
        $lastModificationTime = $this->getRoutesFilesModificationTime($this->routePaths);

        return $lastModificationTime === ($cachedRoutes['@timestamp'] ?? 0);
    }

    protected function writeCacheFile(array $routes)
    {
        ksort($routes);

        $routesContent = '<?php return ' . var_export($routes, true) . ';';
        file_put_contents($this->app->routesCachePath(), $routesContent);
    }

    /**
     * Get routes files last modification timestamp.
     */
    protected function getRoutesFilesModificationTime(array $paths): int
    {
        $lastModificationTime = 0;

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                    $path,
                    \FilesystemIterator::SKIP_DOTS
                ), \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($iterator as $i) {
                    $lastModificationTime = max($lastModificationTime, $i->getMTime());
                }

                continue;
            }

            if (!file_exists($path)) {
                throw new \RuntimeException("Routes file not found in {$path}");
            }

            $modificationTime = filemtime($path);
            $lastModificationTime = max($lastModificationTime, $modificationTime);
        }

        return $lastModificationTime;
    }
}
