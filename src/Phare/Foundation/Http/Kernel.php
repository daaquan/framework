<?php

namespace Phare\Foundation\Http;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;
use Phalcon\Mvc\Micro\Collection;
use Phalcon\Mvc\Router\Route;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\Kernel as HttpKernel;
use Phare\Debug\DebugLogger;
use Phare\Http\Request;
use Phare\Routing\ApplicationModeResolver;
use Phare\Routing\ControllerActionParameterResolver;
use Phare\Routing\DispatchForwardPayloadBuilder;
use Phare\Routing\MiddlewareApplicator;
use Phare\Routing\RouteDataSourceResolver;
use Phare\Routing\RouteLoader;
use Phare\Routing\RouteParamsBinder;
use Phare\Routing\RouteRegistrationOrchestrator;
use Phare\Routing\RoutePatternMatcher;
use Phare\Routing\WebDispatchForwardRegistrar;

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
        $this->applyMiddlewares($this->middlewares);
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
        $allRoutes = (new RouteDataSourceResolver())->resolve(
            $cachedRoutesPath,
            fn (string $path) => $this->loadRoutesWithoutCache($path)
        );
        $mode = (new ApplicationModeResolver())->resolve($this->app);

        (new RouteRegistrationOrchestrator())->register(
            $allRoutes,
            $mode,
            $this->app['router'],
            $this->app['request'],
            $this->routeMiddleware,
            fn (array $routes, string $targetUri, string $targetMethod) => $this->matchParameterizedRoute($routes, $targetUri, $targetMethod),
            fn (array $routeParams) => (new RouteParamsBinder())->bind(
                $routeParams,
                fn (string $name, callable $factory) => $this->app->singleton($name, $factory)
            ),
            fn (array $routeData, array $routeParams) => $this->handleWebRoutes($routeData, $routeParams),
            fn (array $routeData) => $this->handleMicroRoutes($routeData),
            fn (array $middlewares) => $this->applyMiddlewares($middlewares),
        );
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

        $this->applyMiddlewares($this->middlewareGroups['api'] ?? []);

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

        $this->applyMiddlewares($this->middlewareGroups['web'] ?? []);

        // If there are URL params from pattern matching, or typed params to inject, set up dispatch forwarding
        if (empty($routeData['params']) && empty($urlParams)) {
            return;
        }

        (new WebDispatchForwardRegistrar())->register(
            $this->app['eventsManager'],
            $routeData,
            $urlParams,
            fn (array $resolvedRouteData, array $resolvedUrlParams) => (new DispatchForwardPayloadBuilder())->build(
                $resolvedRouteData,
                $resolvedUrlParams,
                fn (array $paramTypes, array $params) => (new ControllerActionParameterResolver())->resolve(
                    $paramTypes,
                    $params,
                    fn (string $type) => $this->app->make($type),
                    fn (RequestInterface $instance) => $this->app->singleton('request', $instance)
                )
            )
        );
    }

    /**
     * Match a URI against parameterized route patterns.
     * Returns [routeData, params] or null.
     */
    protected function matchParameterizedRoute(array $allRoutes, string $uri, string $method): ?array
    {
        return (new RoutePatternMatcher())->match($allRoutes, $uri, $method);
    }

    /**
     * Apply middlewares with consistent logging behavior.
     *
     * @param array<int, string> $middlewares
     */
    protected function applyMiddlewares(array $middlewares): void
    {
        (new MiddlewareApplicator())->apply(
            $middlewares,
            fn (string $middleware) => $this->app->middleware($middleware),
            fn (string $middleware) => $this->debugLogger?->logMiddlewareStart($middleware),
            fn (string $middleware) => $this->debugLogger?->logMiddlewareEnd($middleware),
        );
    }

    /**
     * Load routes on-the-fly without requiring a cache file.
     * Used in development when routes have not been cached.
     */
    protected function loadRoutesWithoutCache(?string $cachedRoutesPath = null): array
    {
        $routeLoader = RouteLoader::create($this->app);
        $routeLoader->generateRoutesCacheFile();

        $cachedRoutesPath ??= $this->app->routesCachePath();

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
