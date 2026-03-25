<?php

use Phare\Routing\RouteParamsBinder;

it('binds route params as singleton when params exist', function () {
    $binder = new RouteParamsBinder();
    $calls = [];

    $binder->bind(
        ['id' => '77'],
        function (string $name, callable $factory) use (&$calls) {
            $calls[] = [$name, $factory()];
        }
    );

    expect($calls)->toBe([['routeParams', ['id' => '77']]]);
});

it('does not bind when route params are empty', function () {
    $binder = new RouteParamsBinder();
    $called = false;

    $binder->bind([], function (string $name, callable $factory) use (&$called) {
        $called = true;
    });

    expect($called)->toBeFalse();
});
