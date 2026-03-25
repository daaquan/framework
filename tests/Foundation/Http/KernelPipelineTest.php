<?php

use Phalcon\Config\Config;
use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application;
use Phare\Foundation\Http\Kernel;

afterEach(function () {
    Mockery::close();
});

test('toggle off keeps native middleware registration behavior', function () {
    $app = new KernelPipelineFakeApplication(false);
    $kernel = new KernelPipelineTestKernel($app);

    $kernel->setGlobalMiddleware([
        KernelPipelineFirstMiddleware::class,
    ]);
    $kernel->setMiddlewareGroups([
        'web' => [KernelPipelineSecondMiddleware::class],
    ]);
    $kernel->setRouteMiddlewareMap([
        'auth' => KernelPipelineThirdMiddleware::class,
    ]);

    $kernel->runSyncGlobalMiddleware();
    $kernel->runSyncMiddlewareGroup('web');
    $kernel->runSyncRouteMiddleware(['auth']);

    expect($app->nativeMiddlewareCalls)->toBe([
        KernelPipelineFirstMiddleware::class,
        KernelPipelineSecondMiddleware::class,
        KernelPipelineThirdMiddleware::class,
    ]);

    expect($kernel->pipelineStack())->toBe([]);
});

test('toggle on runs middleware via pipeline in order around destination', function () {
    $app = new KernelPipelineFakeApplication(true);
    $tracker = new KernelPipelineOrderTracker();
    $app->bindShared(KernelPipelineOrderTracker::class, $tracker);

    $kernel = new KernelPipelineTestKernel($app);
    $kernel->setGlobalMiddleware([
        KernelPipelineFirstMiddleware::class,
    ]);
    $kernel->setMiddlewareGroups([
        'web' => [KernelPipelineSecondMiddleware::class],
    ]);
    $kernel->setRouteMiddlewareMap([
        'auth' => KernelPipelineThirdMiddleware::class,
    ]);

    $kernel->runSyncGlobalMiddleware();
    $kernel->runSyncMiddlewareGroup('web');
    $kernel->runSyncRouteMiddleware(['auth']);

    $request = new Request();
    $response = new Response();

    $result = $kernel->runDispatch($request, function () use ($tracker, $response) {
        $tracker->events[] = 'destination';

        return $response;
    });

    expect($app->nativeMiddlewareCalls)->toBe([])
        ->and($result)->toBe($response)
        ->and($tracker->events)->toBe([
            'first:before',
            'second:before',
            'third:before',
            'destination',
            'third:after',
            'second:after',
            'first:after',
        ]);
});

test('pipeline middleware can short-circuit request lifecycle', function () {
    $app = new KernelPipelineFakeApplication(true);
    $kernel = new KernelPipelineTestKernel($app);

    $events = [];
    $request = new Request();
    $response = new Response();

    $kernel->setGlobalMiddleware([
        function ($request, $next) use (&$events, $response) {
            $events[] = 'short-circuit';

            return $response;
        },
        function ($request, $next) use (&$events) {
            $events[] = 'never-reached';

            return $next($request);
        },
    ]);

    $kernel->runSyncGlobalMiddleware();

    $destinationCalled = false;

    $result = $kernel->runDispatch($request, function () use (&$destinationCalled, $response) {
        $destinationCalled = true;

        return $response;
    });

    expect($result)->toBe($response)
        ->and($destinationCalled)->toBeFalse()
        ->and($events)->toBe(['short-circuit']);
});

test('route middleware alias resolution works with parameters in pipeline mode', function () {
    $app = new KernelPipelineFakeApplication(true);
    $tracker = new KernelPipelineOrderTracker();
    $app->bindShared(KernelPipelineOrderTracker::class, $tracker);

    $kernel = new KernelPipelineTestKernel($app);
    $kernel->setRouteMiddlewareMap([
        'auth' => KernelPipelineAliasMiddleware::class,
    ]);

    $kernel->runSyncRouteMiddleware(['auth:foo,bar']);

    $request = new Request();
    $response = new Response();

    $kernel->runDispatch($request, fn () => $response);

    expect($tracker->events)->toBe(['alias:foo:bar']);
});

class KernelPipelineTestKernel extends Kernel
{
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not used in this test kernel.');
    }

    public function setGlobalMiddleware(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    public function setMiddlewareGroups(array $groups): void
    {
        $this->middlewareGroups = array_merge($this->middlewareGroups, $groups);
    }

    public function setRouteMiddlewareMap(array $map): void
    {
        $this->routeMiddleware = $map;
    }

    public function runSyncGlobalMiddleware(): void
    {
        $this->syncMiddleware();
    }

    public function runSyncMiddlewareGroup(string $group): void
    {
        $this->syncMiddlewareGroup($group);
    }

    public function runSyncRouteMiddleware(array $routeMiddleware): void
    {
        $this->syncRouteMiddleware($routeMiddleware);
    }

    public function runDispatch(RequestInterface $request, Closure $destination): mixed
    {
        return $this->dispatchThroughMiddleware($request, $destination);
    }

    public function pipelineStack(): array
    {
        return $this->pipelineMiddlewareStack();
    }
}

class KernelPipelineFakeApplication extends Container implements Application
{
    public array $nativeMiddlewareCalls = [];

    public function __construct(bool $usePipelineMiddleware)
    {
        parent::__construct();

        $this->setShared('config', new Config([
            'app' => [
                'http' => [
                    'use_pipeline_middleware' => $usePipelineMiddleware,
                ],
            ],
        ]));
    }

    public function bindShared(string $abstract, object $instance): void
    {
        $this->bind($abstract, fn () => $instance, true);
    }

    public function middleware($abstract): void
    {
        $this->nativeMiddlewareCalls[] = $abstract;
    }

    public function version(): string
    {
        return 'test';
    }

    public function basePath(string $path = ''): string
    {
        return '/tmp';
    }

    public function environment(...$environments)
    {
        return 'testing';
    }

    public function runningInConsole()
    {
        return true;
    }

    public function runningUnitTests()
    {
        return true;
    }

    public function bootstrapWith(array $bootstrappers) {}

    public function hasBeenBootstrapped(): bool
    {
        return true;
    }
}

class KernelPipelineOrderTracker
{
    public array $events = [];
}

class KernelPipelineFirstMiddleware
{
    public function __construct(private KernelPipelineOrderTracker $tracker) {}

    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $this->tracker->events[] = 'first:before';
        $response = $next($request);
        $this->tracker->events[] = 'first:after';

        return $response;
    }
}

class KernelPipelineSecondMiddleware
{
    public function __construct(private KernelPipelineOrderTracker $tracker) {}

    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $this->tracker->events[] = 'second:before';
        $response = $next($request);
        $this->tracker->events[] = 'second:after';

        return $response;
    }
}

class KernelPipelineThirdMiddleware
{
    public function __construct(private KernelPipelineOrderTracker $tracker) {}

    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $this->tracker->events[] = 'third:before';
        $response = $next($request);
        $this->tracker->events[] = 'third:after';

        return $response;
    }
}

class KernelPipelineAliasMiddleware
{
    public function __construct(private KernelPipelineOrderTracker $tracker) {}

    public function handle(RequestInterface $request, Closure $next, string $first, string $second): ResponseInterface
    {
        $this->tracker->events[] = "alias:{$first}:{$second}";

        return $next($request);
    }
}
