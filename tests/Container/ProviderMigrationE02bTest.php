<?php

use Phalcon\Di\ServiceProviderInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Foundation\AbstractApplication;
use Phare\Providers\BladeViewProvider;
use Phare\Providers\PasskeyServiceProvider;
use Phare\Providers\RouteServiceProvider;
use Phare\Providers\TranslateProvider;
use Phare\Providers\VoltViewProvider;
use Phare\Support\ServiceProvider;

it('Application contract extends the Container contract', function () {
    expect(is_subclass_of(Application::class, ContainerContract::class))->toBeTrue();
});

it('AbstractApplication implements the Application contract', function () {
    expect(is_subclass_of(AbstractApplication::class, Application::class))->toBeTrue();
});

it('the Application contract declares the app-only helper methods', function () {
    foreach (['basePath', 'storagePath', 'resourcePath', 'languagePath', 'environment', 'routesIsCached', 'has'] as $method) {
        expect(method_exists(Application::class, $method))->toBeTrue($method);
    }
});

it('the 5 remaining providers extend the Phare ServiceProvider base', function () {
    foreach ([
        RouteServiceProvider::class,
        BladeViewProvider::class,
        VoltViewProvider::class,
        TranslateProvider::class,
        PasskeyServiceProvider::class,
    ] as $provider) {
        expect(is_subclass_of($provider, ServiceProvider::class))->toBeTrue($provider);
    }
});

it('no Phare provider still implements the Phalcon ServiceProviderInterface', function () {
    $providers = glob(__DIR__ . '/../../src/Phare/Providers/*Provider.php');

    foreach ($providers as $file) {
        $class = 'Phare\\Providers\\' . basename($file, '.php');
        expect(in_array(ServiceProviderInterface::class, class_implements($class) ?: [], true))
            ->toBeFalse($class);
    }
});
