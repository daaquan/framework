<?php

use Phalcon\Mvc\Router\Exception as RouteException;
use Phare\Routing\RouteSelectionResolver;

it('returns direct route match when static uri and method exist', function () {
    $resolver = new RouteSelectionResolver();
    $allRoutes = [
        '/users' => [
            'GET' => ['action' => 'index'],
        ],
    ];

    [$routeData, $routeParams] = $resolver->resolve(
        $allRoutes,
        '/users',
        'GET',
        fn (array $routes, string $uri, string $method) => null
    );

    expect($routeData)->toBe(['action' => 'index']);
    expect($routeParams)->toBe([]);
});

it('returns parameterized route match when direct match is missing', function () {
    $resolver = new RouteSelectionResolver();
    $allRoutes = [
        '/users/{id}' => [
            'GET' => ['action' => 'show'],
        ],
    ];

    [$routeData, $routeParams] = $resolver->resolve(
        $allRoutes,
        '/users/7',
        'GET',
        fn (array $routes, string $uri, string $method) => [['action' => 'show'], ['id' => '7']]
    );

    expect($routeData)->toBe(['action' => 'show']);
    expect($routeParams)->toBe(['id' => '7']);
});

it('throws RouteException when no route matches', function () {
    $resolver = new RouteSelectionResolver();

    expect(fn () => $resolver->resolve([], '/missing', 'GET', fn () => null))
        ->toThrow(RouteException::class, 'Route "GET /missing" not found.');
});
