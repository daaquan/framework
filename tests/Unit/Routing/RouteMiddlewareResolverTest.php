<?php

use Phare\Routing\RouteMiddlewareResolver;
use Tests\Mock\AuthMiddleware;
use Tests\Mock\ThrottleMiddleware;

it('resolves middleware aliases to concrete middleware classes', function () {
    $resolver = new RouteMiddlewareResolver();

    $resolved = $resolver->resolve(
        ['auth', 'throttle'],
        [
            'auth' => AuthMiddleware::class,
            'throttle' => ThrottleMiddleware::class,
        ]
    );

    expect($resolved)->toBe([
        AuthMiddleware::class,
        ThrottleMiddleware::class,
    ]);
});

it('resolves parameterised aliases such as throttle:5,1', function () {
    $resolver = new RouteMiddlewareResolver();

    $resolved = $resolver->resolve(
        ['throttle:5,1', 'auth'],
        [
            'auth' => AuthMiddleware::class,
            'throttle' => ThrottleMiddleware::class,
        ]
    );

    expect($resolved)->toBe([
        ThrottleMiddleware::class . ':5,1',
        AuthMiddleware::class,
    ]);
});

it('throws when route middleware alias is undefined', function () {
    $resolver = new RouteMiddlewareResolver();

    expect(fn () => $resolver->resolve(['unknown'], []))
        ->toThrow(RuntimeException::class, 'Middleware alias "unknown" not found.');
});
