<?php

use Phalcon\Config\Config;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Router;
use Phare\Contracts\Foundation\Container;
use Phare\Foundation\AbstractApplication;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Log\LogManager;

class MockApplication extends AbstractApplication
{
    protected function createApplication()
    {
        $this->singleton('config', Config::class);

        return (new Micro($this->phalconDi()))
            ->notFound(function () {
                return 'Not found';
            });
    }

    public function handle($uri)
    {
        $this->singleton('request', Request::class);
        $this->singleton('response', Response::class);
        $this->singleton('router', function () {
            return new Router(false);
        });

        return $this->app->handle($uri);
    }

    public function terminate()
    {
        $this->callTerminatingCallbacks();
    }
}

it('can be instantiated', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    expect($app)->toBeInstanceOf(AbstractApplication::class);
    expect($app)->toBeInstanceOf(Container::class);
});

it('has a version', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    expect($app->version())->toEqual('dev');
});

it('can handle a request', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    $this->expectOutputString('Not found');

    $response = $app->handle('/some/uri');
    expect($response)->toBe('Not found');
});

it('loads configurations properly', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    $app->configure('database');
    $config = $app->make('config');

    expect($config->path('database.default'))->toBe('sqlite');
});

it('sets and gets base path correctly', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    $basePath = $app->basePath();

    expect($basePath)->toEqual($_ENV['APP_BASE_PATH']);
});

it('determines if the application is running in the console', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    $runningInConsole = $app->runningInConsole();

    expect($runningInConsole)->toBe(true);
});

it('registers configured providers', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    $app->configure('app');

    $app->registerConfiguredProviders();

    $service = $app->make('log');
    expect($service)->toBeInstanceOf(LogManager::class);
});

it('checks the application environment', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    $previous = getenv('APP_ENV');
    putenv('APP_ENV=testing');

    expect($app->environment('testing'))->toBe(true);
    expect($app->environment('production'))->toBe(false);

    putenv($previous === false ? 'APP_ENV' : "APP_ENV={$previous}");
});

it('bootstrap the application with given bootstrappers', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    $bootstrappers = [
        HandleExceptions::class,
    ];

    $app->bootstrapWith($bootstrappers);

    expect($app->hasBeenBootstrapped())->toBe(true);
});

it('determines if the application has been bootstrapped', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    expect($app->hasBeenBootstrapped())->toBe(false);

    $app->bootstrapWith([HandleExceptions::class]);

    expect($app->hasBeenBootstrapped())->toBe(true);
});

it('runs terminating callbacks through the micro application', function () {
    $app = new Phare\Foundation\Micro($_ENV['APP_BASE_PATH']);

    $called = false;
    $app->terminating(function () use (&$called) {
        $called = true;
    });

    $app->terminate();

    expect($called)->toBeTrue();
});
