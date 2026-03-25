<?php

namespace Phare\Foundation\Http;

use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Micro\Collection;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Router\Exception as RouteException;
use Phalcon\Mvc\Router\Route;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\Kernel as HttpKernel;
use Phare\Contracts\Http\Validation\Validator;
use Phare\Debug\DebugLogger;
use Phare\Foundation\Micro;
use Phare\Foundation\Web;
use Phare\Http\Request;
use Phare\Pipeline\Pipeline;
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
     * Cached value of app.http.use_pipeline_middleware.
     */
    protected ?bool $usePipelineMiddleware = null;

    /**
     * Middleware stack used when pipeline middleware execution is enabled.
     *
     * @var array<int, string|callable>
     */
    protected array $pipelineMiddlewareStack = [];

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

    protected function syncMiddleware(): void
    {
        foreach ($this->middlewares as $middleware) {
            $this->registerMiddleware($middleware);
        }
    }

    protected function syncMiddlewareGroup(string $group): void
    {
        foreach ($this->middlewareGroups[$group] ?? [] as $middleware) {
            $this->registerMiddleware($middleware);
        }
    }

    /**
     * @param array<int, string> $middlewares
     */
    protected function syncRouteMiddleware(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->registerMiddleware($this->resolveRouteMiddlewareAlias($middleware));
        }
    }

    protected function resolveRouteMiddlewareAlias(string $middleware): string
    {
        [$alias, $parameters] = array_pad(explode(':', $middleware, 2), 2, null);

        $resolved = $this->routeMiddleware[$alias] ?? null;

        if ($resolved === null) {
            throw new \RuntimeException("Middleware alias \"{$alias}\" not found.");
        }

        if ($parameters !== null && $parameters !== '') {
            return $resolved . ':' . $parameters;
        }

        return $resolved;
    }

    protected function registerMiddleware(string|callable $middleware): void
    {
        if ($this->shouldUsePipelineMiddleware()) {
            $this->pipelineMiddlewareStack[] = $middleware;

            return;
        }

        $this->debugLogger?->logMiddlewareStart(is_string($middleware) ? $middleware : 'closure');
        $this->app->middleware($middleware);
        $this->debugLogger?->logMiddlewareEnd(is_string($middleware) ? $middleware : 'closure');
    }

    protected function shouldUsePipelineMiddleware(): bool
    {
        if ($this->usePipelineMiddleware !== null) {
            return $this->usePipelineMiddleware;
        }

        if (!$this->app->has('config')) {
            return $this->usePipelineMiddleware = false;
        }

        $config = $this->app->make('config');

        if (is_object($config) && method_exists($config, 'path')) {
            return $this->usePipelineMiddleware = (bool)$config->path('app.http.use_pipeline_middleware', false);
        }

        if (is_array($config)) {
            return $this->usePipelineMiddleware = (bool)($config['app']['http']['use_pipeline_middleware'] ?? false);
        }

        return $this->usePipelineMiddleware = false;
    }

    /**
     * Execute the request lifecycle with middleware handling.
     *
     * When app.http.use_pipeline_middleware is enabled, middleware collected from
     * global + group + route stacks are executed through Phare\Pipeline\Pipeline.
     * Otherwise, request handling falls back to existing Phalcon-native behavior.
     */
    protected function dispatchThroughMiddleware(RequestInterface $request, \Closure $destination): mixed
    {
        if (!$this->shouldUsePipelineMiddleware()) {
            return $destination($request);
        }

        if ($this->pipelineMiddlewareStack === []) {
            return $destination($request);
        }

        return $this->sendThroughPipeline($request, $this->pipelineMiddlewareStack, $destination);
    }

    /**
     * @return array<int, string|callable>
     */
    protected function pipelineMiddlewareStack(): array
    {
        return $this->pipelineMiddlewareStack;
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

        /** @var Router $router */
        $router = $this->app['router'];

        $appClass = get_class($this->app);
        if ($appClass === Web::class) {
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
            throw new RouteException("Route \"$method $uri\" not found.");
        }

        $routeData = $allRoutes[$uri][$method];

        if ($appClass === Micro::class) {
            $this->handleMicroRoutes($routeData);
        } elseif ($appClass === Web::class) {
            $this->handleWebRoutes($routeData);
        } else {
            throw new \RuntimeException("Application class \"{$appClass}\" not supported.");
        }

        $this->syncRouteMiddleware($routeData['middleware'] ?? []);
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

        $this->syncMiddlewareGroup('api');

        $this->app->mount($route);
    }

    protected function handleWebRoutes(array $routeData)
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

        $this->syncMiddlewareGroup('web');

        if (empty($routeData['params'])) {
            return;
        }

        $this->app['eventsManager']->attach('dispatch:beforeExecuteRoute',
            function (Event $event, Dispatcher $dispatcher) use ($routeData) {
                if ($dispatcher->wasForwarded()) {
                    return;
                }

                $dispatcher->forward([
                    'namespace' => $routeData['namespace'],
                    'controller' => $routeData['controller'],
                    'action' => $routeData['action'],
                    'params' => array_map(
                        function ($param) {
                            $instance = $this->app->make($param);

                            // TODO: Move this to middleware
                            if ($instance instanceof Validator) {
                                if (!$instance->validate($instance->all())) {
                                    throw new \RuntimeException('Request validation failed. ' . $instance->getMessages()['message']);
                                }
                            }

                            if ($instance instanceof RequestInterface) {
                                $this->app->singleton('request', $instance);
                            }

                            return $instance;
                        },
                        $routeData['params']
                    ),
                ]);
            });
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

    /**
     * Send the given request through the middleware pipeline.
     *
     * This provides a Laravel-style Pipeline-based middleware execution
     * alternative to Phalcon's native middleware registration.
     *
     * @param array<int, string|callable> $middleware
     */
    protected function sendThroughPipeline(RequestInterface $request, array $middleware, \Closure $then): mixed
    {
        return (new Pipeline($this->app))
            ->send($request)
            ->through($middleware)
            ->then($then);
    }
}
