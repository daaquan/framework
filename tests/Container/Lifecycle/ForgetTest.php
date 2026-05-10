<?php

use Phare\Container\Container;

it('forgetInstance() drops a single shared instance so the next make rebuilds', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $first = $c->make('a');

    $c->forgetInstance('a');

    expect($c->make('a'))->not->toBe($first);
});

it('forgetInstances() drops every shared resolution', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $c->singleton('b', fn () => new stdClass());
    $aFirst = $c->make('a');
    $bFirst = $c->make('b');

    $c->forgetInstances();

    expect($c->make('a'))->not->toBe($aFirst)
        ->and($c->make('b'))->not->toBe($bFirst);
});

it('forgetExtenders() removes registered extenders for an abstract', function () {
    $c = new Container();
    $c->singleton('v', fn () => 'base');
    $c->extend('v', fn ($x) => $x . '+e');

    $c->forgetExtenders('v');
    $c->forgetInstance('v');

    expect($c->make('v'))->toBe('base');
});

it('flush() clears bindings, resolved state, and extenders', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $c->alias('a', 'A');
    $c->extend('a', fn ($x) => $x);
    $c->make('a');

    $c->flush();

    expect($c->bound('a'))->toBeFalse()
        ->and($c->resolved('a'))->toBeFalse();
});
