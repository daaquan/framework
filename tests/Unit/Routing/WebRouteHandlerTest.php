<?php

use Phare\Routing\WebRouteHandler;

beforeEach(function () {
    // Each test builds its own fake app/router so we keep state captures local.
});

it('registers a route on the Phalcon router and binds the controller singleton', function () {
    $routerCalls = [];
    $bindings = [];
    $middlewareApplied = null;

    $router = new class($routerCalls) {
        public function __construct(public array &$calls) {}

        public function add(string $path, array $params)
        {
            $this->calls[] = ['add', $path, $params];

            return new class {
                public function via($method)
                {
                    return $this;
                }

                public function setName($name)
                {
                    return $this;
                }
            };
        }
    };

    $app = new class($bindings, $router) implements ArrayAccess {
        public $eventsManager;

        public function __construct(public array &$bindings, public $router) {}

        public function singleton($name, $value)
        {
            $this->bindings[$name] = $value;
        }

        public function make($abstract)
        {
            return new stdClass();
        }

        public function offsetExists($offset): bool
        {
            return $offset === 'router';
        }

        public function offsetGet($offset): mixed
        {
            return $offset === 'router' ? $this->router : null;
        }

        public function offsetSet($offset, $value): void {}

        public function offsetUnset($offset): void {}
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: function (array $mw) use (&$middlewareApplied) {
            $middlewareApplied = $mw;
        },
        registerForward: function () {},
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
        ->and($bindings)->toHaveKey(\Phalcon\Mvc\ControllerInterface::class)
        ->and($middlewareApplied)->toBe(['SomeMiddleware']);
});

it('registers a dispatch-forward listener when the route has typed params', function () {
    $forwardArgs = null;

    $router = new class {
        public function add($p, $params)
        {
            return new class {
                public function via($m)
                {
                    return $this;
                }

                public function setName($n)
                {
                    return $this;
                }
            };
        }
    };

    $app = new class($router) implements ArrayAccess {
        public $eventsManager;

        public function __construct(public $router) {}

        public function singleton($n, $v) {}

        public function make($a)
        {
            return new stdClass();
        }

        public function offsetExists($o): bool
        {
            return $o === 'router';
        }

        public function offsetGet($o): mixed
        {
            return $o === 'router' ? $this->router : null;
        }

        public function offsetSet($o, $v): void {}

        public function offsetUnset($o): void {}
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: fn ($mw) => null,
        registerForward: function ($app, $routeData, $urlParams) use (&$forwardArgs) {
            $forwardArgs = compact('routeData', 'urlParams');
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

    expect($forwardArgs)->not->toBeNull()
        ->and($forwardArgs['urlParams'])->toBe(['id' => 42]);
});

it('skips forward listener registration when no params are present', function () {
    $forwardCalled = false;

    $router = new class {
        public function add($p, $params)
        {
            return new class {
                public function via($m)
                {
                    return $this;
                }

                public function setName($n)
                {
                    return $this;
                }
            };
        }
    };

    $app = new class($router) implements ArrayAccess {
        public $eventsManager;

        public function __construct(public $router) {}

        public function singleton($n, $v) {}

        public function make($a)
        {
            return new stdClass();
        }

        public function offsetExists($o): bool
        {
            return $o === 'router';
        }

        public function offsetGet($o): mixed
        {
            return $o === 'router' ? $this->router : null;
        }

        public function offsetSet($o, $v): void {}

        public function offsetUnset($o): void {}
    };

    $handler = new WebRouteHandler(
        applyMiddlewares: fn ($mw) => null,
        registerForward: function () use (&$forwardCalled) {
            $forwardCalled = true;
        },
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
