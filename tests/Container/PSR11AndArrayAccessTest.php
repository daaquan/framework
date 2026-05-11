<?php

use Phare\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

it('implements Psr\\Container\\ContainerInterface', function () {
    expect(new Container())->toBeInstanceOf(ContainerInterface::class);
});

it('inherited get() resolves bound abstracts', function () {
    $c = new Container();
    $c->singleton('thing', fn () => 'value');

    expect($c->get('thing'))->toBe('value');
});

it('inherited has() reports bound abstracts', function () {
    $c = new Container();
    $c->singleton('present', fn () => 1);

    expect($c->has('present'))->toBeTrue();
});

it('psrGet() throws NotFoundExceptionInterface for unknown ids', function () {
    $c = new Container();

    try {
        $c->psrGet('does-not-exist-xyz');
        $this->fail('expected NotFoundExceptionInterface');
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
});

it('psrGet() resolves bound abstracts like make()', function () {
    $c = new Container();
    $c->singleton('thing', fn () => 'value');

    expect($c->psrGet('thing'))->toBe('value');
});
