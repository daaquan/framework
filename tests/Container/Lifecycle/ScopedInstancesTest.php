<?php

use Phare\Container\Container;

it('scoped() resolves the same instance until forgetScopedInstances() runs', function () {
    $c = new Container();
    $c->scoped('req', fn () => new stdClass());

    $a = $c->make('req');
    $b = $c->make('req');
    expect($a)->toBe($b);

    $c->forgetScopedInstances();

    expect($c->make('req'))->not->toBe($a);
});

it('scopedIf() skips registration when an existing binding is present', function () {
    $c = new Container();
    $c->scoped('req', fn () => 'first');
    $c->scopedIf('req', fn () => 'second');

    expect($c->make('req'))->toBe('first');
});

it('forgetScopedInstances() only clears scoped bindings, not regular singletons', function () {
    $c = new Container();
    $c->singleton('persistent', fn () => new stdClass());
    $c->scoped('transient', fn () => new stdClass());

    $persistentFirst = $c->make('persistent');
    $transientFirst = $c->make('transient');

    $c->forgetScopedInstances();

    expect($c->make('persistent'))->toBe($persistentFirst)
        ->and($c->make('transient'))->not->toBe($transientFirst);
});
