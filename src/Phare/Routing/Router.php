<?php

namespace Phare\Routing;

use Phare\Contracts\Routing\Router as RouterContract;

class Router implements RouterContract
{
    private array $routes = [];

    public function group($options, $callback)
    {
        $currentGroup = new static();
        $callback($currentGroup);
        foreach ($currentGroup->getRoutes() as $route) {
            foreach ($options as $key => $value) {
                $route[$key] = $value;
            }
            $this->routes[] = $route;
        }
    }

    public function get($path, $handler, $middleware = [])
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put($path, $handler, $middleware = [])
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete($path, $handler, $middleware = [])
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function patch($path, $handler, $middleware = [])
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function options($path, $handler, $middleware = [])
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    public function addParams($params)
    {
        $this->routes[count($this->routes) - 1]['params'] = $params;

        return $this;
    }

    public function name($name)
    {
        $this->routes[count($this->routes) - 1]['name'] = $name;
    }

    public function addRoute($method, $path, $handler, $middleware = [])
    {
        $path = '/' . trim($path, '/');

        [$controller, $action] = $this->parseHandler($handler);

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            // 'namespace' => $namespace,
            'controller' => $controller,
            'action' => $action,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Resolve a route handler into a [controller, action] pair.
     *
     * Supports the "Controller@method" string form as well as closures and
     * invokable objects (stored as the controller with a null action).
     *
     * @return array{0: mixed, 1: string|null}
     */
    private function parseHandler($handler): array
    {
        if ($handler instanceof \Closure || is_object($handler)) {
            return [$handler, null];
        }

        if (is_array($handler) && count($handler) === 2) {
            return [$handler[0], $handler[1]];
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $action] = explode('@', $handler, 2);

            if ($controller !== '' && $action !== '') {
                return [$controller, $action];
            }
        }

        throw new \InvalidArgumentException(
            'Route handler must be a "Controller@method" string, a [class, method] array, a closure, or an invokable object.'
        );
    }

    public function resource($path, $controller, $middleware = [])
    {
        $path = trim($path, '/');
        $name = strtolower(str_replace('/', '.', $path));

        $this->get("/{$path}", "{$controller}@index", $middleware)->name("{$name}.index");
        $this->get("/{$path}/create", "{$controller}@create", $middleware)->name("{$name}.create");
        $this->post("/{$path}", "{$controller}@store", $middleware)->name("{$name}.store");
        $this->get("/{$path}/{id}", "{$controller}@show", $middleware)->name("{$name}.show");
        $this->get("/{$path}/{id}/edit", "{$controller}@edit", $middleware)->name("{$name}.edit");
        $this->put("/{$path}/{id}", "{$controller}@update", $middleware)->name("{$name}.update");
        $this->delete("/{$path}/{id}", "{$controller}@destroy", $middleware)->name("{$name}.destroy");

        return $this;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function isMatched($uri, $method)
    {
        return array_filter($this->routes, function ($route) use ($uri, $method) {
            return $route['path'] === $uri && $route['method'] === $method;
        });
    }
}
