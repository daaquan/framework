<?php

use Phare\Routing\RoutePatternMatcher;

it('matches parameterized route and returns named params', function () {
    $matcher = new RoutePatternMatcher();
    $routes = [
        '/users/{id}' => [
            'GET' => ['action' => 'show'],
        ],
    ];

    $matched = $matcher->match($routes, '/users/42', 'GET');

    expect($matched)->not->toBeNull();
    expect($matched[0])->toBe(['action' => 'show']);
    expect($matched[1])->toBe(['id' => '42']);
});

it('supports inline regex constraints in parameterized routes', function () {
    $matcher = new RoutePatternMatcher();
    $routes = [
        '/articles/{slug<[a-z0-9\\-]+>}' => [
            'GET' => ['action' => 'show'],
        ],
    ];

    $matched = $matcher->match($routes, '/articles/hello-world-9', 'GET');

    expect($matched)->not->toBeNull();
    expect($matched[1])->toBe(['slug' => 'hello-world-9']);
});

it('returns null when constrained parameter does not match', function () {
    $matcher = new RoutePatternMatcher();
    $routes = [
        '/orders/{id<[0-9]+>}' => [
            'GET' => ['action' => 'show'],
        ],
    ];

    $matched = $matcher->match($routes, '/orders/abc', 'GET');

    expect($matched)->toBeNull();
});

it('ignores route definitions that do not contain requested http method', function () {
    $matcher = new RoutePatternMatcher();
    $routes = [
        '/users/{id}' => [
            'POST' => ['action' => 'store'],
        ],
    ];

    $matched = $matcher->match($routes, '/users/1', 'GET');

    expect($matched)->toBeNull();
});
