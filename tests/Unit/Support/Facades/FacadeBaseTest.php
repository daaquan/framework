<?php

use Phare\Container\Container;
use Phare\Support\Facades\Facade;

class FooService
{
    public function ping(): string
    {
        return 'real';
    }
}

class FooFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'foo';
    }
}

class BarFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bar';
    }
}

beforeEach(function () {
    Facade::clearResolvedInstances();
    $this->app = new Container();
    $this->app->instance('foo', new FooService());
    Facade::setFacadeApplication($this->app);
});

it('swap() replaces the facade root for calls and getFacadeRoot()', function () {
    FooFacade::swap(new class()
    {
        public function ping(): string
        {
            return 'swapped';
        }
    });

    expect(FooFacade::ping())->toBe('swapped');
    expect(FooFacade::getFacadeRoot()->ping())->toBe('swapped');
    // The swap is also reflected in the container.
    expect($this->app->make('foo')->ping())->toBe('swapped');
});

it('clearResolvedInstance() drops a single swap so the cache no longer shadows the container', function () {
    FooFacade::swap(new class()
    {
        public function ping(): string
        {
            return 'swapped';
        }
    });
    FooFacade::clearResolvedInstance();
    // Reset the container binding; without the cache shadow, this is what resolves.
    $this->app->instance('foo', new FooService());

    expect(FooFacade::getFacadeRoot())->toBeInstanceOf(FooService::class);
});

it('clearResolvedInstances() clears all swaps', function () {
    FooFacade::swap(new class()
    {
        public function ping(): string
        {
            return 'x';
        }
    });
    Facade::clearResolvedInstances();
    $this->app->instance('foo', new FooService());

    expect(FooFacade::getFacadeRoot())->toBeInstanceOf(FooService::class);
});

it('resolved() fires immediately when the accessor is already bound', function () {
    $captured = null;
    FooFacade::resolved(function ($service) use (&$captured) {
        $captured = $service;
    });

    expect($captured)->toBeInstanceOf(FooService::class);
});

it('resolved() fires on a later resolution when not yet bound', function () {
    $hit = false;
    BarFacade::resolved(function () use (&$hit) {
        $hit = true;
    });

    expect($hit)->toBeFalse();

    $this->app->bind('bar', fn () => new FooService());
    $this->app->make('bar');

    expect($hit)->toBeTrue();
});
