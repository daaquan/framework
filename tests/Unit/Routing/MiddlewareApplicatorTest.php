<?php

use Phare\Routing\MiddlewareApplicator;

it('applies middleware in the same order as provided', function () {
    $applicator = new MiddlewareApplicator();
    $applied = [];

    $applicator->apply(
        ['First', 'Second', 'Third'],
        function (string $middleware) use (&$applied) {
            $applied[] = $middleware;
        }
    );

    expect($applied)->toBe(['First', 'Second', 'Third']);
});

it('invokes start and end callbacks around each middleware', function () {
    $applicator = new MiddlewareApplicator();
    $events = [];

    $applicator->apply(
        ['Auth', 'Throttle'],
        function (string $middleware) use (&$events) {
            $events[] = "apply:{$middleware}";
        },
        function (string $middleware) use (&$events) {
            $events[] = "start:{$middleware}";
        },
        function (string $middleware) use (&$events) {
            $events[] = "end:{$middleware}";
        }
    );

    expect($events)->toBe([
        'start:Auth',
        'apply:Auth',
        'end:Auth',
        'start:Throttle',
        'apply:Throttle',
        'end:Throttle',
    ]);
});
