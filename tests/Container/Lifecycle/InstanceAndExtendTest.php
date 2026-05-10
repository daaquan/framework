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
