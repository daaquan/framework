<?php

use Phare\Container\Container;

it('instance() registers a pre-built object and reports it resolved', function () {
    $c = new Container();
    $obj = new stdClass();
    $obj->mark = 'pre-built';

    $returned = $c->instance('foo', $obj);

    expect($returned)->toBe($obj)
        ->and($c->make('foo'))->toBe($obj)
        ->and($c->resolved('foo'))->toBeTrue()
        ->and($c->bound('foo'))->toBeTrue();
});

it('instance() fires rebinding callbacks when replacing an existing binding', function () {
    $c = new Container();
    $c->singleton('foo', fn () => new stdClass());
    $c->make('foo');

    $rebindings = [];
    $c->rebinding('foo', function ($app, $new) use (&$rebindings) {
        $rebindings[] = $new;
    });

    $replacement = new stdClass();
    $c->instance('foo', $replacement);

    expect($rebindings)->toHaveCount(1)
        ->and($rebindings[0])->toBe($replacement)
        ->and($c->make('foo'))->toBe($replacement);
});

it('instance() does not fire rebinding for first-time bindings', function () {
    $c = new Container();
    $rebindings = [];
    $c->rebinding('foo', function ($app, $new) use (&$rebindings) {
        $rebindings[] = $new;
    });

    $c->instance('foo', new stdClass());

    expect($rebindings)->toBe([]);
});

it('extend() decorates the value returned by a binding on the next make()', function () {
    $c = new Container();
    $c->singleton('value', fn () => 'hello');

    $c->extend('value', fn ($v, $app) => $v . ' world');

    expect($c->make('value'))->toBe('hello world');
});

it('extend() applies extenders in registration order', function () {
    $c = new Container();
    $c->singleton('value', fn () => 'a');
    $c->extend('value', fn ($v) => $v . 'b');
    $c->extend('value', fn ($v) => $v . 'c');

    expect($c->make('value'))->toBe('abc');
});

it('extend() replaces an already-resolved shared instance and fires rebinding', function () {
    $c = new Container();
    $obj = new stdClass();
    $obj->n = 1;
    $c->instance('thing', $obj);

    $first = $c->make('thing');

    $rebindings = [];
    $c->rebinding('thing', function ($app, $new) use (&$rebindings) {
        $rebindings[] = $new->n;
    });

    $c->extend('thing', function ($v, $app) {
        $clone = clone $v;
        $clone->n = $clone->n + 10;

        return $clone;
    });

    expect($c->make('thing')->n)->toBe(11)
        ->and($rebindings)->toBe([11])
        ->and($c->make('thing'))->not->toBe($first);
});
