<?php

use Phare\Routing\ApplicationModeResolver;
use Phare\Routing\RouteMountDispatcher;

it('dispatches to web handler for web mode', function () {
    $dispatcher = new RouteMountDispatcher();
    $called = [];

    $dispatcher->dispatch(
        ApplicationModeResolver::MODE_WEB,
        function () use (&$called) {
            $called[] = 'web';
        },
        function () use (&$called) {
            $called[] = 'micro';
        }
    );

    expect($called)->toBe(['web']);
});

it('dispatches to micro handler for micro mode', function () {
    $dispatcher = new RouteMountDispatcher();
    $called = [];

    $dispatcher->dispatch(
        ApplicationModeResolver::MODE_MICRO,
        function () use (&$called) {
            $called[] = 'web';
        },
        function () use (&$called) {
            $called[] = 'micro';
        }
    );

    expect($called)->toBe(['micro']);
});

it('throws for unsupported mode', function () {
    $dispatcher = new RouteMountDispatcher();

    expect(fn () => $dispatcher->dispatch('unknown', fn () => null, fn () => null))
        ->toThrow(RuntimeException::class, 'Unsupported application mode "unknown".');
});
