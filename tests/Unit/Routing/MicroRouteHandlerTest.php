<?php

use Phare\Routing\MicroRouteHandler;

it('mounts a Phalcon micro Collection with controller, prefix, and method', function () {
    $mounted = null;
    $appliedMiddlewares = null;

    $app = new class($mounted) {
        public function __construct(public &$mounted) {}

        public function mount($collection)
        {
            $this->mounted = $collection;
        }
    };

    $handler = new MicroRouteHandler(
        applyMiddlewares: function (array $mw) use (&$appliedMiddlewares) {
            $appliedMiddlewares = $mw;
        },
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

        public function mount($c)
        {
            $this->mounted = $c;
        }
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
