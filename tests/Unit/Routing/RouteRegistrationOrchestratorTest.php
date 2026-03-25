<?php

use Phare\Routing\ApplicationModeResolver;
use Phare\Routing\RouteRegistrationOrchestrator;

class OrchestratorFakeRequest
{
    public function __construct(
        private string $uri,
        private string $method
    ) {
    }

    public function getURI(bool $local = false): string
    {
        return $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}

class OrchestratorFakeRoute
{
    public ?string $method = null;
    public string $name = '';

    public function via(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}

class OrchestratorFakeRouter
{
    public array $adds = [];

    public function add(string $path, array $definition): OrchestratorFakeRoute
    {
        $route = new OrchestratorFakeRoute();
        $this->adds[] = [
            'path' => $path,
            'definition' => $definition,
            'route' => $route,
        ];

        return $route;
    }
}

it('orchestrates web flow with hydration, selection, binding, mount, and middleware application', function () {
    $orchestrator = new RouteRegistrationOrchestrator();
    $router = new OrchestratorFakeRouter();
    $request = new OrchestratorFakeRequest('/users/9', 'GET');
    $events = [];

    $allRoutes = [
        '/users/{id}' => [
            'GET' => [
                'path' => '/users/{id}',
                'namespace' => 'App\\Http\\Controllers',
                'controller' => 'User',
                'action' => 'show',
                'method' => 'GET',
                'name' => 'users.show',
                'middleware' => ['auth'],
            ],
        ],
    ];

    $orchestrator->register(
        $allRoutes,
        ApplicationModeResolver::MODE_WEB,
        $router,
        $request,
        ['auth' => 'AuthMiddleware'],
        fn (array $routes, string $uri, string $method) => [
            $routes['/users/{id}']['GET'],
            ['id' => '9'],
        ],
        function (array $routeParams) use (&$events) {
            $events[] = ['bind', $routeParams];
        },
        function (array $routeData, array $routeParams) use (&$events) {
            $events[] = ['mount_web', $routeData['action'], $routeParams];
        },
        function (array $routeData) use (&$events) {
            $events[] = ['mount_micro', $routeData['action']];
        },
        function (array $middlewares) use (&$events) {
            $events[] = ['apply', $middlewares];
        }
    );

    expect($router->adds)->toHaveCount(1);
    expect($events)->toBe([
        ['bind', ['id' => '9']],
        ['mount_web', 'show', ['id' => '9']],
        ['apply', ['AuthMiddleware']],
    ]);
});

it('orchestrates micro flow without web hydration', function () {
    $orchestrator = new RouteRegistrationOrchestrator();
    $router = new OrchestratorFakeRouter();
    $request = new OrchestratorFakeRequest('/health', 'GET');
    $events = [];

    $allRoutes = [
        '/health' => [
            'GET' => [
                'path' => '/health',
                'namespace' => 'App\\Http\\Controllers',
                'controller' => 'Health',
                'action' => 'index',
                'method' => 'GET',
                'middleware' => [],
            ],
        ],
    ];

    $orchestrator->register(
        $allRoutes,
        ApplicationModeResolver::MODE_MICRO,
        $router,
        $request,
        [],
        fn (array $routes, string $uri, string $method) => null,
        function (array $routeParams) use (&$events) {
            $events[] = ['bind', $routeParams];
        },
        function (array $routeData, array $routeParams) use (&$events) {
            $events[] = ['mount_web', $routeData['action'], $routeParams];
        },
        function (array $routeData) use (&$events) {
            $events[] = ['mount_micro', $routeData['action']];
        },
        function (array $middlewares) use (&$events) {
            $events[] = ['apply', $middlewares];
        }
    );

    expect($router->adds)->toHaveCount(0);
    expect($events)->toBe([
        ['bind', []],
        ['mount_micro', 'index'],
        ['apply', []],
    ]);
});
