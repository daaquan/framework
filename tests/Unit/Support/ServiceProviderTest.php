<?php

use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Contracts\Support\DeferrableProvider;
use Phare\Support\ServiceProvider;

/** Container subclass that records boot-lifecycle callbacks for assertions. */
function makeBootSpyApp(): Container
{
    return new class() extends Container
    {
        /** @var array<string, list<Closure>> */
        public array $callbacks = ['booting' => [], 'booted' => []];

        public function booting(Closure $callback): void
        {
            $this->callbacks['booting'][] = $callback;
        }

        public function booted(Closure $callback): void
        {
            $this->callbacks['booted'][] = $callback;
        }
    };
}

test('ServiceProvider constructor does not depend on Phalcon', function () {
    $param = (new ReflectionMethod(ServiceProvider::class, '__construct'))->getParameters()[0];

    expect((string)$param->getType())->not->toContain('Phalcon');
});

test('ServiceProvider accepts any Phare container', function () {
    $provider = new class(new Container()) extends ServiceProvider
    {
        public function register(): void {}
    };

    expect($provider)->toBeInstanceOf(ServiceProvider::class);
});

test('ServiceProvider constructor param is typed to the container contract', function () {
    $param = (new ReflectionMethod(ServiceProvider::class, '__construct'))->getParameters()[0];

    expect((string)$param->getType())->toContain(ContainerContract::class);
});

test('provides() returns an empty array by default', function () {
    $provider = new class(new Container()) extends ServiceProvider
    {
        public function register(): void {}
    };

    expect($provider->provides())->toBe([]);
});

test('booting() and booted() delegate to the application boot lifecycle', function () {
    $app = makeBootSpyApp();

    $provider = new class($app) extends ServiceProvider
    {
        public function register(): void {}

        public function boot(): void
        {
            $this->booting(function () {});
            $this->booted(function () {});
        }
    };

    $provider->boot();

    expect($app->callbacks['booting'])->toHaveCount(1);
    expect($app->callbacks['booted'])->toHaveCount(1);
});

test('a provider may implement the DeferrableProvider contract', function () {
    $provider = new class(new Container()) extends ServiceProvider implements DeferrableProvider
    {
        public function register(): void {}

        public function provides(): array
        {
            return ['my.service'];
        }
    };

    expect($provider)->toBeInstanceOf(DeferrableProvider::class);
    expect($provider->provides())->toBe(['my.service']);
});
