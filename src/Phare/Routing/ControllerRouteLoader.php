<?php

namespace Phare\Routing;

use FilesystemIterator;
use Phare\Attributes\Route as RouteAttr;
use Phare\Attributes\RouteAttribute;
use Phare\Collections\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class ControllerRouteLoader extends RouteLoader
{
    /**
     * Generate the routes cache file.
     */
    public function generateRoutesCacheFile(): void
    {
        $routes = ['@timestamp' => $this->getRoutesFilesModificationTime($this->routePaths)];
        $router = $this->registerRoutesFromControllerAttributes($this->routePaths);
        $routes += $this->extractRoutes($router);

        $this->writeCacheFile($routes);
    }

    protected function registerRoutesFromControllerAttributes(array $paths): Router
    {
        $controllerDir = $paths[0];
        $baseControllerPath = $controllerDir . '/Controller.php';

        if (!file_exists($baseControllerPath)) {
            throw new \RuntimeException("Base controller not found: $baseControllerPath");
        }

        $defaultNamespace = $this->extractNamespaceFromFile($baseControllerPath);
        if (!$defaultNamespace) {
            throw new \RuntimeException("Could not determine namespace from $baseControllerPath");
        }

        $router = new Router();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            if (!$this->isUnderPaths($file->getPath(), $paths)) {
                continue;
            }

            $className = $this->buildClassName($file, $controllerDir, $defaultNamespace);
            if (!$className) {
                continue;
            }

            try {
                $classReflection = new ReflectionClass($className);
            } catch (ReflectionException) {
                throw new \RuntimeException("Unable to reflect class: $className");
            }

            if (!$classReflection->isInstantiable()) {
                continue;
            }

            $classAttr = $this->getFirstClassAttribute($classReflection, RouteAttribute::class) ?: new RouteAttribute();

            foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $routeAttr = $this->getFirstMethodAttribute($method, RouteAttr::class);
                if (!$routeAttr) {
                    continue;
                }

                $params = array_map(fn ($p) => $p->getType()?->getName(), $method->getParameters());

                foreach ($routeAttr->getMethods() as $httpMethod) {
                    $router->addRoute(
                        $httpMethod,
                        $this->buildRoutePattern($file, $controllerDir, $routeAttr->getPattern()),
                        "$className@{$method->getName()}",
                        array_merge($classAttr->getMiddlewares(), $routeAttr->getMiddlewares())
                    )->addParams($params)
                        ->name($routeAttr->getName());
                }
            }
        }

        return $router;
    }

    private function extractNamespaceFromFile(string $file): ?string
    {
        $fp = fopen($file, 'rb');
        while ($fp && $line = fgets($fp)) {
            if (preg_match('/^namespace\s+(.+);/', $line, $m)) {
                fclose($fp);

                return $m[1];
            }
        }
        $fp && fclose($fp);

        return null;
    }

    private function isUnderPaths(string $filePath, array $paths): bool
    {
        foreach ($paths as $path) {
            if (str_starts_with($filePath, $path)) {
                return true;
            }
        }

        return false;
    }

    private function buildClassName($file, string $baseDir, string $namespace): ?string
    {
        $relativePath = substr($file->getPath(), strlen($baseDir));
        $ns = trim(str_replace('/', '\\', $relativePath), '\\');

        return $namespace . ($ns ? "\\$ns" : '') . '\\' . $file->getBasename('.php');
    }

    private function getFirstClassAttribute(ReflectionClass $rc, string $attrClass)
    {
        $attrs = $rc->getAttributes($attrClass, ReflectionAttribute::IS_INSTANCEOF);

        return $attrs ? $attrs[0]->newInstance() : null;
    }

    private function getFirstMethodAttribute(ReflectionMethod $method, string $attrClass)
    {
        $attrs = $method->getAttributes($attrClass);

        return $attrs ? $attrs[0]->newInstance() : null;
    }

    private function buildRoutePattern($file, string $baseDir, string $pattern): string
    {
        $relativePath = trim(substr($file->getPath(), strlen($baseDir)), '/');
        $prefix = strtolower(Str::kebabCase($relativePath));

        return trim($prefix . '/' . $pattern, '/');
    }
}
