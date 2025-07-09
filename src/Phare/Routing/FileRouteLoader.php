<?php

namespace Phare\Routing;

class FileRouteLoader extends RouteLoader
{
    /**
     * Generate the routes cache file.
     */
    public function generateRoutesCacheFile(): void
    {
        $routes = ['@timestamp' => $this->getRoutesFilesModificationTime($this->routePaths)];
        foreach ($this->routePaths as $routeFile) {
            $fileRouter = require $routeFile;
            if (!$fileRouter instanceof \Phare\Routing\Router) {
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

                $routes = [...$routes, ...$this->extractRoutes($router)];
            }
        }

        $this->writeCacheFile($routes);
    }
}
