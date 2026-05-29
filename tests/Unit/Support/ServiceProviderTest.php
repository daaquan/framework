<?php

use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\ServiceProvider;

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
