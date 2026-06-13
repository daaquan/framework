<?php

namespace Phare\Routing;

class FileRouteLoader extends RouteLoader
{
    /**
     * Generate the routes cache file.
     */
    public function generateRoutesCacheFile(): void
    {
        $extracted = [];
        foreach ($this->routePaths as $routeFile) {
            $fileRouter = require $routeFile;
            if (!$fileRouter instanceof Router) {
                continue;
            }

            $router = new Router();
            foreach ($fileRouter->getRoutes() as $route) {
                $className = $route['controller'];
                $method = $route['action'];

                try {
                    $classReflection = new \ReflectionClass($className);
                } catch (\ReflectionException $e) {
                    throw new \RuntimeException("Unable to reflect class {$className}");
                }

                if (!$classReflection->isInstantiable()) {
                    continue;
                }

                foreach ($classReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $methodReflection) {
                    if ($methodReflection->getName() !== $method) {
                        continue;
                    }

                    $params = array_map(function ($p) {
                        return $p->getType()?->getName();
                    }, $methodReflection->getParameters());

                    $router->addRoute($route['method'], ($route['prefix'] ?? null) . '/' . $route['path'],
                        $className . '@' . $method, $route['middleware'])
                        ->addParams($params)
                        ->name($route['name'] ?? null);
                }

                // Accumulate per-route extractions and merge once below to
                // avoid repeated O(n) spreads and accidental @timestamp clobbering.
                $extracted[] = $this->extractRoutes($router);
            }
        }

        $routes = $extracted === [] ? [] : array_merge(...$extracted);

        // Set @timestamp last so accumulated route entries can never clobber it.
        $routes['@timestamp'] = $this->getRoutesFilesModificationTime($this->routePaths);

        $this->writeCacheFile($routes);
    }
}
