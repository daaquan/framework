# Phase 3 Finalize: Kernel::registerRoutes() wiring-only orchestration

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce `Foundation\Http\Kernel::registerRoutes()` and its directly-supporting helpers (`handleWebRoutes`, `handleMicroRoutes`, `matchParameterizedRoute`, `applyMiddlewares`) to pure wiring — the Kernel should only construct and connect routing collaborators, never implement routing behavior inline.

**Architecture:** Extract the two remaining inline implementations — `handleWebRoutes` and `handleMicroRoutes` — into dedicated handler classes under `src/Phare/Routing/`. Each new class owns one branch of route registration (web Phalcon Router vs micro Collection mount). The Kernel becomes a thin composition layer that selects handlers, passes them into the existing `RouteRegistrationOrchestrator`, and exposes injection seams for testing.

**Tech Stack:** PHP 8.2+, Phalcon 5.4+, Pest PHP. References:
- `/opt/framework/src/Phare/Foundation/Http/Kernel.php:203-291` (current state — registerRoutes + handleWebRoutes + handleMicroRoutes + matchParameterizedRoute).
- `/opt/framework/src/Phare/Routing/RouteRegistrationOrchestrator.php` (the orchestrator that takes the callbacks today).
- `/opt/framework/src/Phare/Routing/WebDispatchForwardRegistrar.php` (an existing handler that demonstrates the extraction style).
- `/opt/framework/tests/Unit/Foundation/Http/KernelOrchestrationTest.php` (existing harness for kernel routing flow).
- `/opt/framework/docs/laravel13-phalcon-architecture.md` (roadmap, Phase 3 + "Next Implementation Slice").

---

## File Structure

### New files
- `src/Phare/Routing/WebRouteHandler.php` — encapsulates the "register a single web route against the Phalcon Router" flow (controller binding + Router::add + via/setName + middleware apply + WebDispatchForwardRegistrar wiring).
- `src/Phare/Routing/MicroRouteHandler.php` — encapsulates the "mount a single micro Collection" flow (Collection setHandler/setPrefix/$method/applyMiddlewares + app->mount).
- `tests/Unit/Routing/WebRouteHandlerTest.php` — behavior tests for WebRouteHandler.
- `tests/Unit/Routing/MicroRouteHandlerTest.php` — behavior tests for MicroRouteHandler.

### Modified files
- `src/Phare/Foundation/Http/Kernel.php` — `registerRoutes()` becomes pure wiring; `handleWebRoutes`/`handleMicroRoutes` deleted; `matchParameterizedRoute` deleted (inlined as `RoutePatternMatcher` call); `applyMiddlewares` kept (it owns debug-logger plumbing) but the inline path that builds the closure becomes more explicit.
- `tests/Unit/Foundation/Http/KernelOrchestrationTest.php` — update expectations now that handlers are injectable.
- `docs/laravel13-phalcon-architecture.md` — mark Phase 3 wiring-only milestone as landed.

### Files explicitly NOT touched
- `RouteRegistrationOrchestrator` — its public API is already correct; the handlers slot into the same callback shape.
- All other `Phare\Routing\*` extracted helpers from earlier Phase 3 work — unchanged.

---

## Task 1: Carve out WebRouteHandler

### Files
- Create: `src/Phare/Routing/WebRouteHandler.php`
- Test: `tests/Unit/Routing/WebRouteHandlerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Phare\Routing\WebRouteHandler;

it('registers a route on the Phalcon router and binds the controller singleton', function () {
    $appBindings = [];
    $appMakes = [];
    $routerCalls = [];

    $router = new class($routerCalls) {
        public function __construct(public array &$calls) {}
        public function add(string $path, array $params)
        {
            $this->calls[] = ['add', $path, $params];
            return new class {
                public function via($method) { return $this; }
                public function setName($name) { return $this; }
            };
        }
    };

    $app = new class($appBindings, $appMakes, $router) {
        public function __construct(public array &$bindings, public array &$makes, public $router) {}
        public function singleton($name, $value) { $this->bindings[$name] = $value; }
        public function make($abstract) { $this->makes[] = $abstract; return new \stdClass(); }
        public function offsetGet($k) { return $this->router; }
        public $eventsManager;
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: function (array $mw) use (&$middlewareApplied) { $middlewareApplied = $mw; },
        registerForward: function () { /* not used in this test */ },
    );

    $routeData = [
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'Home',
        'action' => 'index',
        'path' => '/',
        'method' => 'GET',
        'name' => 'home',
        'params' => [],
    ];

    $handler->handle($app, $routeData, [], ['SomeMiddleware']);

    expect($routerCalls)->toHaveCount(1)
        ->and($routerCalls[0][1])->toBe('/')
        ->and($appBindings)->toHaveKey(\Phalcon\Mvc\ControllerInterface::class)
        ->and($middlewareApplied)->toBe(['SomeMiddleware']);
});

it('registers a dispatch-forward listener when route has typed params or url params', function () {
    $forwardCalled = false;

    $router = new class { public function add($p, $params) { return new class { public function via($m) { return $this; } public function setName($n) { return $this; } }; } };
    $app = new class($router) {
        public function __construct(public $router) {}
        public function singleton($n, $v) {}
        public function make($a) { return new \stdClass(); }
        public function offsetGet($k) { return $this->router; }
        public $eventsManager;
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: fn ($mw) => null,
        registerForward: function ($app, $routeData, $urlParams) use (&$forwardCalled) {
            $forwardCalled = true;
        },
    );

    $routeData = [
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'Show',
        'action' => 'show',
        'path' => '/items/{id}',
        'method' => 'GET',
        'name' => 'items.show',
        'params' => ['id' => 'int'],
    ];

    $handler->handle($app, $routeData, ['id' => 42], []);

    expect($forwardCalled)->toBeTrue();
});

it('skips forward listener registration when no params are present', function () {
    $forwardCalled = false;

    $router = new class { public function add($p, $params) { return new class { public function via($m) { return $this; } public function setName($n) { return $this; } }; } };
    $app = new class($router) {
        public function __construct(public $router) {}
        public function singleton($n, $v) {}
        public function make($a) { return new \stdClass(); }
        public function offsetGet($k) { return $this->router; }
        public $eventsManager;
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: fn ($mw) => null,
        registerForward: function () use (&$forwardCalled) { $forwardCalled = true; },
    );

    $routeData = [
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'Home',
        'action' => 'index',
        'path' => '/',
        'method' => 'GET',
        'name' => 'home',
        'params' => [],
    ];

    $handler->handle($app, $routeData, [], []);

    expect($forwardCalled)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Routing/WebRouteHandlerTest.php
```

Expected: FAIL with `Class "Phare\Routing\WebRouteHandler" not found`.

- [ ] **Step 3: Implement WebRouteHandler**

Create `src/Phare/Routing/WebRouteHandler.php`:

```php
<?php

namespace Phare\Routing;

use Closure;
use Phalcon\Mvc\ControllerInterface;

class WebRouteHandler
{
    /**
     * @param Closure(array<int, string|callable>): void $applyMiddlewares
     * @param Closure(object, array, array): void $registerForward
     */
    public function __construct(
        private Closure $applyMiddlewares,
        private Closure $registerForward,
    ) {}

    /**
     * @param array<string, mixed> $routeData
     * @param array<string, mixed> $urlParams
     * @param array<int, string|callable> $webGroupMiddleware
     */
    public function handle(object $app, array $routeData, array $urlParams, array $webGroupMiddleware): void
    {
        $class = "{$routeData['namespace']}\\{$routeData['controller']}Controller";
        $app->singleton(ControllerInterface::class, $app->make($class));

        $route = $app['router']->add($routeData['path'], [
            'namespace' => $routeData['namespace'],
            'controller' => $routeData['controller'],
            'action' => $routeData['action'],
        ]);
        $route->via($routeData['method'])
            ->setName($routeData['name'] ?? '');

        ($this->applyMiddlewares)($webGroupMiddleware);

        if (empty($routeData['params']) && empty($urlParams)) {
            return;
        }

        ($this->registerForward)($app, $routeData, $urlParams);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Routing/WebRouteHandlerTest.php
```

Expected: all 3 tests pass.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Routing/WebRouteHandler.php tests/Unit/Routing/WebRouteHandlerTest.php
git commit -m "feat(routing): extract WebRouteHandler for web route registration flow"
```

---

## Task 2: Carve out MicroRouteHandler

### Files
- Create: `src/Phare/Routing/MicroRouteHandler.php`
- Test: `tests/Unit/Routing/MicroRouteHandlerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Phare\Routing\MicroRouteHandler;

it('mounts a Phalcon micro Collection with controller, prefix, and method', function () {
    $mounted = null;
    $appliedMiddlewares = null;

    $app = new class($mounted) {
        public function __construct(public &$mounted) {}
        public function mount($collection) { $this->mounted = $collection; }
    };

    $handler = new MicroRouteHandler(
        applyMiddlewares: function (array $mw) use (&$appliedMiddlewares) { $appliedMiddlewares = $mw; },
    );

    $routeData = [
        'namespace' => 'App\\Http\\Controllers\\Api',
        'controller' => 'Users',
        'action' => 'index',
        'path' => '/users',
        'method' => 'get',
        'prefix' => '/api',
        'name' => 'users.index',
    ];

    $handler->handle($app, $routeData, ['ApiAuthMiddleware']);

    expect($mounted)->toBeInstanceOf(\Phalcon\Mvc\Micro\Collection::class)
        ->and($appliedMiddlewares)->toBe(['ApiAuthMiddleware']);
});

it('mounts without a prefix when routeData omits it', function () {
    $mounted = null;
    $app = new class($mounted) {
        public function __construct(public &$mounted) {}
        public function mount($c) { $this->mounted = $c; }
    };

    $handler = new MicroRouteHandler(applyMiddlewares: fn () => null);

    $handler->handle($app, [
        'namespace' => 'App\\Http\\Controllers\\Api',
        'controller' => 'Status',
        'action' => 'index',
        'path' => '/status',
        'method' => 'get',
    ], []);

    expect($mounted)->toBeInstanceOf(\Phalcon\Mvc\Micro\Collection::class);
});
```

- [ ] **Step 2: Verify failure**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Routing/MicroRouteHandlerTest.php
```

Expected: `Class "Phare\Routing\MicroRouteHandler" not found`.

- [ ] **Step 3: Implement MicroRouteHandler**

Create `src/Phare/Routing/MicroRouteHandler.php`:

```php
<?php

namespace Phare\Routing;

use Closure;
use Phalcon\Mvc\Micro\Collection;

class MicroRouteHandler
{
    /**
     * @param Closure(array<int, string|callable>): void $applyMiddlewares
     */
    public function __construct(private Closure $applyMiddlewares) {}

    /**
     * @param array<string, mixed> $routeData
     * @param array<int, string|callable> $apiGroupMiddleware
     */
    public function handle(object $app, array $routeData, array $apiGroupMiddleware): void
    {
        $route = new Collection();
        $route->setHandler("{$routeData['namespace']}\\{$routeData['controller']}Controller", true);

        if (isset($routeData['prefix'])) {
            $route->setPrefix($routeData['prefix']);
        }

        $method = $routeData['method'];
        $route->$method($routeData['path'], $routeData['action'], $routeData['name'] ?? '');

        ($this->applyMiddlewares)($apiGroupMiddleware);

        $app->mount($route);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Routing/MicroRouteHandlerTest.php
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Routing/MicroRouteHandler.php tests/Unit/Routing/MicroRouteHandlerTest.php
git commit -m "feat(routing): extract MicroRouteHandler for micro Collection mount flow"
```

---

## Task 3: Rewire Kernel to wiring-only via handlers

### Files
- Modify: `src/Phare/Foundation/Http/Kernel.php`
- Test: `tests/Unit/Foundation/Http/KernelOrchestrationTest.php` (extend)

- [ ] **Step 1: Add a kernel-level test asserting handler delegation**

In `tests/Unit/Foundation/Http/KernelOrchestrationTest.php` append:

```php
it('delegates web route registration to WebRouteHandler', function () {
    // Wire a kernel test double whose registerRoutes() runs the orchestrator
    // with mocked handlers; assert the web handler's handle() is invoked once
    // with the expected routeData/urlParams/middleware shape.
    $invoked = null;

    $kernel = $this->makeKernelWithFakeHandlers(
        webHandler: new class($invoked) extends \Phare\Routing\WebRouteHandler {
            public function __construct(public &$invoked) { parent::__construct(fn () => null, fn () => null); }
            public function handle(object $app, array $routeData, array $urlParams, array $mw): void
            {
                $this->invoked = compact('routeData', 'urlParams', 'mw');
            }
        },
        microHandler: new \Phare\Routing\MicroRouteHandler(fn () => null),
        routes: [['mode' => 'web', 'namespace' => 'App', 'controller' => 'Home', 'action' => 'index', 'path' => '/', 'method' => 'GET']],
    );

    $kernel->registerRoutesForTest();

    expect($invoked)->not->toBeNull()
        ->and($invoked['routeData']['controller'])->toBe('Home');
});

it('delegates micro route registration to MicroRouteHandler', function () {
    $invoked = null;

    $kernel = $this->makeKernelWithFakeHandlers(
        webHandler: new \Phare\Routing\WebRouteHandler(fn () => null, fn () => null),
        microHandler: new class($invoked) extends \Phare\Routing\MicroRouteHandler {
            public function __construct(public &$invoked) { parent::__construct(fn () => null); }
            public function handle(object $app, array $routeData, array $mw): void
            {
                $this->invoked = compact('routeData', 'mw');
            }
        },
        routes: [['mode' => 'micro', 'namespace' => 'App\\Api', 'controller' => 'Users', 'action' => 'index', 'path' => '/users', 'method' => 'get']],
    );

    $kernel->registerRoutesForTest();

    expect($invoked)->not->toBeNull()
        ->and($invoked['routeData']['controller'])->toBe('Users');
});
```

> `makeKernelWithFakeHandlers` is a Pest helper to add to `tests/Pest.php` — it should construct a minimal Application + concrete Kernel subclass exposing `registerRoutesForTest()` that calls the protected `registerRoutes()`. Use the same pattern the existing KernelOrchestrationTest uses for app stubbing.

- [ ] **Step 2: Verify failure**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Foundation/Http/KernelOrchestrationTest.php --filter="delegates"
```

Expected: FAIL — the kernel does not yet delegate to handler objects.

- [ ] **Step 3: Refactor Kernel::registerRoutes() to wiring-only**

Replace the `registerRoutes()` body in `src/Phare/Foundation/Http/Kernel.php`:

```php
protected function registerRoutes(): void
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
        registerForward: fn (object $app, array $routeData, array $urlParams) =>
            (new WebDispatchForwardRegistrar())->register(
                $app->eventsManager,
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
        fn (array $routes, string $targetUri, string $targetMethod) =>
            (new RoutePatternMatcher())->match($routes, $targetUri, $targetMethod),
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
```

Then **delete**:
- `protected function handleMicroRoutes(array $routeData)` (lines around 229–244 of current file)
- `protected function handleWebRoutes(array $routeData, array $urlParams = [])` (lines around 246–282)
- `protected function matchParameterizedRoute(array $allRoutes, string $uri, string $method): ?array` (lines around 288–291)

Add injection seams at the class top:

```php
/** @var WebRouteHandler|null seam for tests */
protected ?WebRouteHandler $webRouteHandler = null;

/** @var MicroRouteHandler|null seam for tests */
protected ?MicroRouteHandler $microRouteHandler = null;

/** Test-only setter; production callers go through registerRoutes()'s defaults. */
public function setRouteHandlersForTesting(?WebRouteHandler $web, ?MicroRouteHandler $micro): void
{
    $this->webRouteHandler = $web;
    $this->microRouteHandler = $micro;
}
```

Add the imports:

```php
use Phare\Routing\MicroRouteHandler;
use Phare\Routing\WebRouteHandler;
```

- [ ] **Step 4: Run the entire kernel-related test set**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Foundation tests/Unit/Routing
```

Expected: green across all tests, including the new delegation tests and the existing `KernelOrchestrationTest` flow.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Foundation/Http/Kernel.php tests/Unit/Foundation/Http/KernelOrchestrationTest.php tests/Pest.php
git commit -m "refactor(kernel): registerRoutes() becomes wiring-only via Web/MicroRouteHandler"
```

---

## Task 4: Run the full test suite + style check

- [ ] **Step 1: Run all framework tests**

```bash
cd /opt/framework && ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 2: Run pint check**

```bash
cd /opt/framework && ./vendor/bin/pint --test
```

Expected: no style violations. If any, run `./vendor/bin/pint` and commit the fixes as `style: pint`.

- [ ] **Step 3: Run app-side smoke**

```bash
cd /opt/phare && composer update phare/framework && ./vendor/bin/pest
```

Expected: green.

- [ ] **Step 4: Manual route smoke**

```bash
cd /opt/phare && php artisan serve &
SERVER_PID=$!
sleep 2
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/
kill $SERVER_PID
```

Expected: `200`.

---

## Task 5: Update roadmap

### Files
- Modify: `docs/laravel13-phalcon-architecture.md`

- [ ] **Step 1: Mark Phase 3 milestone**

In the Phase 3 section, append:

```
Phase 3 final wiring slice (2026-05-11):
- `Kernel::registerRoutes()` reduced to wiring-only orchestration.
- Web/micro route handling extracted into dedicated handlers:
  - `src/Phare/Routing/WebRouteHandler.php`
  - `src/Phare/Routing/MicroRouteHandler.php`
- `handleWebRoutes`, `handleMicroRoutes`, `matchParameterizedRoute` removed from Kernel.
```

Update "Next Implementation Slice" section: drop bullet 1 (`finalize Kernel::registerRoutes()`); bullet 2 (`align container semantics with additional Laravel 13 edge behaviors`) becomes the new Phase 6 entry point (handled in a separate plan).

- [ ] **Step 2: Commit**

```bash
cd /opt/framework
git add docs/laravel13-phalcon-architecture.md
git commit -m "docs: Phase 3 final wiring slice landed"
```

---

## Notes

- Each task lands as one focused commit. If any extraction reveals an unforeseen coupling, revert that commit and re-plan inline before continuing.
- Avoid touching unrelated routing helpers — the goal is wiring-only, not a wider routing refactor.
- The kernel's protected `webRouteHandler`/`microRouteHandler` properties exist only as test seams; production code paths still construct the handlers inline so default behavior is preserved.
- After this plan lands, the kernel file should sit comfortably under ~300 LOC (current 353 LOC minus the deleted handlers, plus the small wiring additions).
