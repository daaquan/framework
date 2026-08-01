<?php

use Phare\Routing\WebRouterHydrator;

class FakeHydratedRoute
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

class FakeHydrationRouter
{
    public array $added = [];

    public function add(string $path, array $definition): FakeHydratedRoute
    {
        $route = new FakeHydratedRoute();
        $this->added[] = [
            'path' => $path,
            'definition' => $definition,
            'route' => $route,
        ];

        return $route;
    }
}

it('hydrates router with all nested route definitions', function () {
    $hydrator = new WebRouterHydrator();
    $router = new FakeHydrationRouter();

    $hydrator->hydrate($router, [
        '/users' => [
            'GET' => [
                'path' => '/users',
                'namespace' => 'App\\Http\\Controllers',
                'controller' => 'User',
                'action' => 'index',
                'method' => 'GET',
                'name' => 'users.index',
            ],
        ],
    ]);

    expect($router->added)->toHaveCount(1);
    expect($router->added[0]['path'])->toBe('/users');
    expect($router->added[0]['definition'])->toBe([
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'User',
        'action' => 'index',
    ]);
    expect($router->added[0]['route']->method)->toBe('GET');
    expect($router->added[0]['route']->name)->toBe('users.index');
});

it('ignores non-array route groups safely', function () {
    $hydrator = new WebRouterHydrator();
    $router = new FakeHydrationRouter();

    $hydrator->hydrate($router, [
        '/users' => 'invalid',
        '/posts' => [
            'GET' => [
                'path' => '/posts',
                'namespace' => 'App\\Http\\Controllers',
                'controller' => 'Post',
                'action' => 'index',
                'method' => 'GET',
            ],
        ],
    ]);

    expect($router->added)->toHaveCount(1);
    expect($router->added[0]['path'])->toBe('/posts');
});
