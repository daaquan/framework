<?php

namespace Phare\Foundation\Http;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\Kernel as HttpKernel;
use Phare\Debug\DebugLogger;
use Phare\Http\Request;
use Phare\Pipeline\Pipeline;
use Phare\Routing\ApplicationModeResolver;
use Phare\Routing\ControllerActionParameterResolver;
use Phare\Routing\DispatchForwardPayloadBuilder;
use Phare\Routing\MicroRouteHandler;
use Phare\Routing\MiddlewareApplicator;
use Phare\Routing\RouteDataSourceResolver;
use Phare\Routing\RouteLoader;
use Phare\Routing\RouteParamsBinder;
use Phare\Routing\RoutePatternMatcher;
use Phare\Routing\RouteRegistrationOrchestrator;
use Phare\Routing\WebDispatchForwardRegistrar;
use Phare\Routing\WebRouteHandler;

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
     * Injection seam used by tests to swap the web route handler.
     */
    protected ?WebRouteHandler $webRouteHandler = null;

    /**
     * Injection seam used by tests to swap the micro route handler.
     */
    protected ?MicroRouteHandler $microRouteHandler = null;

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
        $allRoutes = (new RouteDataSourceResolver())->resolve(
            $cachedRoutesPath,
            fn (string $path) => $this->loadRoutesWithoutCache($path)
        );
        $mode = (new ApplicationModeResolver())->resolve($this->app);

        $applyMiddlewares = fn (array $middlewares) => $this->applyMiddlewares($middlewares);

        $webHandler = $this->webRouteHandler ?? new WebRouteHandler(
            applyMiddlewares: $applyMiddlewares,
            registerForward: fn (object $app, array $routeData, array $urlParams) => (new WebDispatchForwardRegistrar())->register(
                $app['eventsManager'],
                $routeData,
                $urlParams,
                fn (array $resolvedRouteData, array $resolvedUrlParams) => (new DispatchForwardPayloadBuilder())->build(
                    $resolvedRouteData,
                    $resolvedUrlParams,
                    fn (array $paramTypes, array $params) => (new ControllerActionParameterResolver())->resolve(
                        $paramTypes,
                        $params,
                        fn (string $type) => $app->make($type),
                        fn (RequestInterface $instance) => $app->singleton('request', $instance)
                    )
                )
            ),
        );

        $microHandler = $this->microRouteHandler ?? new MicroRouteHandler(
            applyMiddlewares: $applyMiddlewares,
        );

        (new RouteRegistrationOrchestrator())->register(
            $allRoutes,
            $mode,
            $this->app['router'],
            $this->app['request'],
            $this->routeMiddleware,
            fn (array $routes, string $targetUri, string $targetMethod) => (new RoutePatternMatcher())->match($routes, $targetUri, $targetMethod),
            fn (array $routeParams) => (new RouteParamsBinder())->bind(
                $routeParams,
                fn (string $name, callable $factory) => $this->app->singleton($name, $factory)
            ),
            fn (array $routeData, array $routeParams) => $webHandler->handle(
                $this->app,
                $routeData,
                $routeParams,
                $this->middlewareGroups['web'] ?? []
            ),
            fn (array $routeData) => $microHandler->handle(
                $this->app,
                $routeData,
                $this->middlewareGroups['api'] ?? []
            ),
            $applyMiddlewares,
        );
    }

    /**
     * Apply middlewares with consistent logging behavior.
     *
     * @param array<int, string|callable> $middlewares
     */
    protected function applyMiddlewares(array $middlewares): void
    {
        (new MiddlewareApplicator())->apply(
            $middlewares,
            fn (string|callable $middleware) => $this->app->middleware($middleware),
            fn (string|callable $middleware) => is_string($middleware) ? $this->debugLogger?->logMiddlewareStart($middleware) : null,
            fn (string|callable $middleware) => is_string($middleware) ? $this->debugLogger?->logMiddlewareEnd($middleware) : null,
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
