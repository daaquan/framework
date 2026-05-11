<?php

use Phare\Container\Container;

it('beforeResolving() fires for the specific abstract before build', function () {
    $c = new Container();
    $c->bind('thing', fn () => new stdClass());

    $events = [];
    $c->beforeResolving('thing', function ($abstract, $parameters, $container) use (&$events) {
        $events[] = ['specific', $abstract];
    });

    $c->make('thing');

    expect($events)->toBe([['specific', 'thing']]);
});

it('beforeResolving() with no abstract fires globally before every build', function () {
    $c = new Container();
    $c->bind('a', fn () => new stdClass());
    $c->bind('b', fn () => new stdClass());

    $seen = [];
    $c->beforeResolving(function ($abstract) use (&$seen) {
        $seen[] = $abstract;
    });

    $c->make('a');
    $c->make('b');

    expect($seen)->toBe(['a', 'b']);
});

it('currentlyResolving() returns the build stack while resolving', function () {
    $c = new Container();
    $c->bind('thing', fn () => new stdClass());

    $captured = null;
    $c->resolving('thing', function ($instance, $container) use (&$captured) {
        $captured = $container->currentlyResolving();
    });

    $c->make('thing');

    expect($captured)->toBeArray()
        ->and($captured)->toContain('thing');
});

class RefreshTestTarget
{
    public mixed $received = null;

    public function setBus(mixed $bus): void
    {
        $this->received = $bus;
    }
}

it('refresh() couples a rebinding callback to the target method', function () {
    $c = new Container();
    $c->singleton('bus', fn () => new stdClass());

    $target = new RefreshTestTarget();

    $initial = $c->refresh('bus', $target, 'setBus');

    expect($initial)->toBeInstanceOf(stdClass::class)
        ->and($target->received)->toBeNull();

    $replacement = new stdClass();
    $c->instance('bus', $replacement);

    expect($target->received)->toBe($replacement);
});
