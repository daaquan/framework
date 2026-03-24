<?php

namespace Phare\Foundation\Http;

use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Micro\Collection;
use Phalcon\Mvc\Router\Exception as RouteException;
use Phalcon\Mvc\Router\Route;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\Kernel as HttpKernel;
use Phare\Contracts\Http\Validation\Validator;
use Phare\Debug\DebugLogger;
use Phare\Http\Request;
use Phare\Routing\RouteLoader;

abstract class Kernel implements HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     */
    protected array $middlewares = [];

    protected array $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array <string, string>
     */
    protected array $routeMiddleware = [];

    /**
     * The bootstrap classes for the application.
     */
    protected array $bootstrappers = [];

    protected ?DebugLogger $debugLogger = null;

    /**
     * Create a new HTTP kernel instance.
     *
     * @return void
     */
    public function __construct(protected Application $app)
    {
        $this->bootstrap();

        $this->debugLogger = $this->app->has('debugLogger') ? $this->app->make('debugLogger') : null;
        $this->debugLogger?->logServiceProviderBooting();

        $this->registerRoutes();
        $this->debugLogger?->logRouteMounted();

        $this->syncMiddleware();
    }

    protected function syncMiddleware()
    {
        foreach ($this->middlewares as $alias) {
            $this->debugLogger?->logMiddlewareStart($alias);
            $this->app->middleware($alias);
            $this->debugLogger?->logMiddlewareEnd($alias);
        }
    }

    abstract public function handle(RequestInterface $request): ResponseInterface;

    /**
     * Bootstrap the application for HTTP requests.
     */
    public function bootstrap()
    {
        if ($this->app->hasBeenBootstrapped()) {
            return;
        }

        $this->app->bootstrapWith($this->bootstrappers);
    }

    protected function registerRoutes()
    {
        $cachedRoutesPath = $this->app->routesCachePath();

        if (file_exists($cachedRoutesPath)) {
            $allRoutes = require $cachedRoutesPath;
        } else {
            $allRoutes = $this->loadRoutesWithoutCache();
        }

        /** @var \Phalcon\Mvc\Router $router */
        $router = $this->app['router'];

        $appClass = get_class($this->app);
        if ($appClass === \Phare\Foundation\Web::class) {
            foreach ($allRoutes as $routes) {
                if (!is_array($routes)) {
                    continue;
                }
                foreach ($routes as $routeData) {
                    $route = $router->add($routeData['path'], [
                        'namespace' => $routeData['namespace'],
                        'controller' => $routeData['controller'],
                        'action' => $routeData['action'],
                    ]);
                    $route->via($routeData['method'])
                        ->setName($routeData['name'] ?? '');
                }
            }
        }

        /** @var Request $request */
        $request = $this->app['request'];
        $uri = $request->getURI(true) ?: '/';
        $method = $request->getMethod();

        if (!isset($allRoutes[$uri][$method])) {
            // Try pattern matching for parameterized routes
            $matched = $this->matchParameterizedRoute($allRoutes, $uri, $method);
            if ($matched === null) {
                throw new RouteException("Route \"$method $uri\" not found.");
            }
            [$routeData, $routeParams] = $matched;
            // Store matched URL params for controller access
            if (!empty($routeParams)) {
                $this->app->singleton('routeParams', fn() => $routeParams);
            }
        } else {
            $routeData = $allRoutes[$uri][$method];
            $routeParams = [];
        }

        if ($appClass === \Phare\Foundation\Micro::class) {
            $this->handleMicroRoutes($routeData);
        } elseif ($appClass === \Phare\Foundation\Web::class) {
            $this->handleWebRoutes($routeData, $routeParams ?? []);
        } else {
            throw new \RuntimeException("Application class \"{$appClass}\" not supported.");
        }

        foreach ($routeData['middleware'] ?? [] as $alias) {
            $middleware = $this->routeMiddleware[$alias] ?? null;
            if ($middleware === null) {
                throw new \RuntimeException("Middleware alias \"{$alias}\" not found.");
            }
            $this->debugLogger?->logMiddlewareStart($middleware);
            $this->app->middleware($middleware);
            $this->debugLogger?->logMiddlewareEnd($middleware);
        }
    }

    protected function handleMicroRoutes(array $routeData)
    {
        $route = new Collection();
        $route->setHandler("{$routeData['namespace']}\\{$routeData['controller']}Controller", true);

        if (isset($routeData['prefix'])) {
            $route->setPrefix($routeData['prefix']);
        }

        $method = $routeData['method'];
        $route->$method($routeData['path'], $routeData['action'], $routeData['name'] ?? '');

        foreach ($this->middlewareGroups['api'] ?? [] as $alias) {
            $this->debugLogger?->logMiddlewareStart($alias);
            $this->app->middleware($alias);
            $this->debugLogger?->logMiddlewareEnd($alias);
        }

        $this->app->mount($route);
    }

    protected function handleWebRoutes(array $routeData, array $urlParams = [])
    {
        $class = "{$routeData['namespace']}\\{$routeData['controller']}Controller";
        $this->app->singleton(ControllerInterface::class, $this->app->make($class));

        /** @var Route $route */
        $route = $this->app['router']->add($routeData['path'], [
            'namespace' => $routeData['namespace'],
            'controller' => $routeData['controller'],
            'action' => $routeData['action'],
        ]);
        $route->via($routeData['method'])
            ->setName($routeData['name'] ?? '');

        foreach ($this->middlewareGroups['web'] ?? [] as $alias) {
            $this->debugLogger?->logMiddlewareStart($alias);
            $this->app->middleware($alias);
            $this->debugLogger?->logMiddlewareEnd($alias);
        }

        // If there are URL params from pattern matching, or typed params to inject, set up dispatch forwarding
        if (empty($routeData['params']) && empty($urlParams)) {
            return;
        }

        $this->app['eventsManager']->attach('dispatch:beforeExecuteRoute',
            function (Event $event, Dispatcher $dispatcher) use ($routeData, $urlParams) {
                if ($dispatcher->wasForwarded()) {
                    return;
                }

                // Build params array for dispatch
                $params = [];
                $paramTypes = $routeData['params'] ?? [];

                // If no typed params but have URL params, pass URL param values as positional args
                if (empty($paramTypes) && !empty($urlParams)) {
                    $dispatcher->forward([
                        'namespace'  => $routeData['namespace'],
                        'controller' => $routeData['controller'],
                        'action'     => $routeData['action'],
                        'params'     => array_values($urlParams),
                    ]);
                    return;
                }

                // For each declared param type, inject Request or URL param values
                foreach ($paramTypes as $i => $paramType) {
                    if ($paramType === null) {
                        // Untyped - pass URL param value by position
                        $params[] = array_values($urlParams)[$i] ?? null;
                        continue;
                    }

                    // Check if this is a scalar type (string, int) - use URL param value
                    if (in_array($paramType, ['string', 'int', 'float', 'bool'], true)) {
                        $value = array_values($urlParams)[$i] ?? null;
                        settype($value, $paramType);
                        $params[] = $value;
                        continue;
                    }

                    $instance = $this->app->make($paramType);

                    // TODO: Move this to middleware
                    if ($instance instanceof Validator) {
                        if (!$instance->validate($instance->all())) {
                            throw new \RuntimeException('Request validation failed. ' . $instance->getMessages()['message']);
                        }
                    }

                    if ($instance instanceof RequestInterface) {
                        $this->app->singleton('request', $instance);
                    }

                    $params[] = $instance;
                }

                $dispatcher->forward([
                    'namespace'  => $routeData['namespace'],
                    'controller' => $routeData['controller'],
                    'action'     => $routeData['action'],
                    'params'     => $params,
                ]);
            });
    }

    /**
     * Match a URI against parameterized route patterns.
     * Returns [routeData, params] or null.
     */
    protected function matchParameterizedRoute(array $allRoutes, string $uri, string $method): ?array
    {
        foreach ($allRoutes as $pattern => $methods) {
            if (!is_array($methods) || !isset($methods[$method])) {
                continue;
            }

            // Convert {param} pattern to regex
            if (!str_contains($pattern, '{')) {
                continue;
            }

            $paramNames = [];
            $regex = preg_replace_callback('/\{([\w\-]+)(?:<([^>]+)>)?\}/', function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                $regex = $matches[2] ?? '[\w\-]+';
                return "($regex)";
            }, $pattern);

            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($paramNames, $matches);
                return [$methods[$method], $params];
            }
        }

        return null;
    }

    /**
     * Load routes on-the-fly without requiring a cache file.
     * Used in development when routes have not been cached.
     */
    protected function loadRoutesWithoutCache(): array
    {
        $routeLoader = RouteLoader::create($this->app);
        $routeLoader->generateRoutesCacheFile();

        $cachedRoutesPath = $this->app->routesCachePath();

        if (!file_exists($cachedRoutesPath)) {
            throw new \RuntimeException('Failed to generate routes cache.');
        }

        return require $cachedRoutesPath;
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        $this->app->terminate();

        $this->debugLogger?->logTerminate();
    }

    public function getApplication(): Application
    {
        return $this->app;
    }
}
