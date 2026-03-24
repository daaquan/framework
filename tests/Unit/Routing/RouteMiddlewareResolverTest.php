<?php

use Phare\Routing\RouteMiddlewareResolver;

it('resolves middleware aliases to concrete middleware classes', function () {
    $resolver = new RouteMiddlewareResolver();

    $resolved = $resolver->resolve(
        ['auth', 'throttle'],
        [
            'auth' => \Tests\Mock\AuthMiddleware::class,
            'throttle' => \Tests\Mock\ThrottleMiddleware::class,
        ]
    );

    expect($resolved)->toBe([
        \Tests\Mock\AuthMiddleware::class,
        \Tests\Mock\ThrottleMiddleware::class,
    ]);
});

it('throws when route middleware alias is undefined', function () {
    $resolver = new RouteMiddlewareResolver();

    expect(fn () => $resolver->resolve(['unknown'], []))
        ->toThrow(RuntimeException::class, 'Middleware alias "unknown" not found.');
});
