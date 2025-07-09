<?php

namespace Phare\Contracts\Routing;

interface Router
{
    public function group($options, $callback);

    public function get($path, $handler, $middleware = []);

    public function post($path, $handler, $middleware = []);

    public function put($path, $handler, $middleware = []);

    public function delete($path, $handler, $middleware = []);

    public function patch($path, $handler, $middleware = []);

    public function options($path, $handler, $middleware = []);

    public function resource($path, $controller, $middleware = []);

    public function addRoute($method, $path, $handler, $middleware = []);
}
