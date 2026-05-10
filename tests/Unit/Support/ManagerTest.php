<?php

use Phare\Container\Container;
use Phare\Support\Manager;

class ManagerTestDriverA
{
    public string $name = 'a';

    public function ping(): string
    {
        return 'pong-a';
    }
}

class ManagerTestDriverB
{
    public string $name = 'b';
}

class ManagerTestFake extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return 'a';
    }

    protected function createADriver(): ManagerTestDriverA
    {
        return new ManagerTestDriverA();
    }

    protected function createBDriver(): ManagerTestDriverB
    {
        return new ManagerTestDriverB();
    }
}

class ManagerTestNullDefault extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return null;
    }
}

beforeEach(function () {
    $this->container = new Container();
});

it('resolves the default driver when no name is supplied', function () {
    $m = new ManagerTestFake($this->container);

    expect($m->driver())->toBeInstanceOf(ManagerTestDriverA::class);
});

it('caches resolved drivers by name', function () {
    $m = new ManagerTestFake($this->container);

    expect($m->driver('a'))->toBe($m->driver('a'));
});

it('resolves named drivers via createXxxDriver convention', function () {
    $m = new ManagerTestFake($this->container);

    expect($m->driver('b'))->toBeInstanceOf(ManagerTestDriverB::class);
});

it('throws when an unknown driver is requested', function () {
    $m = new ManagerTestFake($this->container);

    $m->driver('unknown');
})->throws(InvalidArgumentException::class, 'Driver [unknown] not supported.');

it('invokes registered custom creators ahead of createXxxDriver', function () {
    $m = new ManagerTestFake($this->container);

    $m->extend('custom', fn ($app) => 'CUSTOM_VALUE');

    expect($m->driver('custom'))->toBe('CUSTOM_VALUE');
});

it('custom creator overrides matching createXxxDriver method', function () {
    $m = new ManagerTestFake($this->container);

    $m->extend('a', fn () => 'OVERRIDDEN');

    expect($m->driver('a'))->toBe('OVERRIDDEN');
});

it('forgetDrivers() clears the resolved cache', function () {
    $m = new ManagerTestFake($this->container);

    $first = $m->driver('a');
    $m->forgetDrivers();
    $second = $m->driver('a');

    expect($second)->not->toBe($first);
});

it('__call() forwards to the default driver', function () {
    $m = new ManagerTestFake($this->container);

    expect($m->ping())->toBe('pong-a');
});

it('throws when the default driver name is null', function () {
    $m = new ManagerTestNullDefault($this->container);

    $m->driver();
})->throws(InvalidArgumentException::class, 'Unable to resolve NULL driver');

it('extend() binds the closure to the manager subclass', function () {
    $m = new ManagerTestFake($this->container);

    $m->extend('introspect', function () {
        return static::class;
    });

    expect($m->driver('introspect'))->toBe(ManagerTestFake::class);
});

it('getContainer() returns the injected container', function () {
    $m = new ManagerTestFake($this->container);

    expect($m->getContainer())->toBe($this->container);
});

it('setContainer() replaces the injected container fluently', function () {
    $m = new ManagerTestFake($this->container);
    $other = new Container();

    expect($m->setContainer($other))->toBe($m)
        ->and($m->getContainer())->toBe($other);
});
