<?php

use Phalcon\Di\DiInterface;
use Phare\Container\Container;

it('basic bind and make returns concrete', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());

    expect($c->make('foo'))->toBeInstanceOf(stdClass::class);
});

it('singleton returns same instance', function () {
    $c = new Container();
    $c->singleton('foo', fn () => new stdClass());

    expect($c->make('foo'))->toBe($c->make('foo'));
});

it('bind default is not shared', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());

    expect($c->make('foo'))->not->toBe($c->make('foo'));
});

it('closure singleton returning array bypasses phalcon store', function () {
    $c = new Container();
    $c->singleton('arr', fn () => ['a' => 1]);

    expect($c->make('arr'))->toBe(['a' => 1]);
    expect($c->make('arr'))->toBe($c->make('arr'));
});

it('alias resolves to target', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());
    $c->alias('foo', 'bar');

    expect($c->make('bar'))->toBeInstanceOf(stdClass::class);
});

it('is shared reflects binding', function () {
    $c = new Container();
    $c->singleton('s', fn () => new stdClass());
    $c->bind('b', fn () => new stdClass());

    expect($c->isShared('s'))->toBeTrue();
    expect($c->isShared('b'))->toBeFalse();
});

it('container is a phalcon di interface', function () {
    expect(new Container())->toBeInstanceOf(DiInterface::class);
});

it('getshared delegates to phalcon store', function () {
    $c = new Container();
    $c->set('svc', fn () => new stdClass(), true);

    expect($c->getShared('svc'))->toBeInstanceOf(stdClass::class);
});

it('exposes inner phalcon di accessor', function () {
    $c = new Container();
    expect($c->phalconDi())->toBeInstanceOf(DiInterface::class);
});
