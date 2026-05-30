<?php

use Phare\Attributes\Route;
use Phare\Attributes\RouteAttribute;

test('route attribute detects and fetches route parameters', function () {
    $route = new Route('/users/{id<\d+>}/posts/{slug}', ['GET', 'POST'], ['auth'], 'user.posts');

    expect($route->hasParams())->toBeTrue();
    expect($route->fetchParams())->toBe([
        'id' => '\d+',
        'slug' => '',
    ]);
    expect($route->getMethods())->toBe(['GET', 'POST']);
    expect($route->getMiddlewares())->toBe(['auth']);
    expect($route->getName())->toBe('user.posts');
});

test('route attribute returns empty parameters for static routes', function () {
    $route = new Route('/health');

    expect($route->hasParams())->toBeFalse();
    expect($route->fetchParams())->toBe([]);
});

test('class route attribute exposes middleware parameters', function () {
    $attribute = new RouteAttribute(['auth', 'verified']);

    expect($attribute->getMiddlewares())->toBe(['auth', 'verified']);
    expect($attribute->getParameters())->toBe([
        'middleware' => ['auth', 'verified'],
    ]);
});
