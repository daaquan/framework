<?php

// We're using a mock here because AbstractApplication is an abstract class.
// You'll need to create a concrete implementation for testing purposes.

class MockApplication extends \Phare\Foundation\AbstractApplication
{
    protected function createApplication()
    {
        $this->singleton('config', \Phalcon\Config\Config::class);

        // Return the actual application instance you want to test, e.g., Micro or other.
        return (new Phalcon\Mvc\Micro())
            ->notFound(function () {
                return 'Not found';
            });
    }

    public function handle($uri)
    {
        $this->setDI($this->app->getDI());
        $this->singleton('request', Phalcon\Http\Request::class);
        $this->singleton('response', Phalcon\Http\Response::class);
        $this->singleton('router', function () {
            return new Phalcon\Mvc\Router(false);
        });

        return $this->app->handle($uri);
    }

    public function terminate()
    {
        $this->app->stop();
    }
}

it('can be instantiated', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    expect($app)->toBeInstanceOf(Phare\Foundation\AbstractApplication::class);
    expect($app)->toBeInstanceOf(Phare\Contracts\Foundation\Container::class);
});

it('has a version', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    expect($app->version())->toEqual('dev');
});

it('can handle a request', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    // You might need to mock the handle method or set expectations depending on its functionality.
    $response = $app->handle('/some/uri');
    expect($response)->not()->toBeNull();
    // Add more assertions based on what handle() should be doing.
});

it('loads configurations properly', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    $app->configure('database');
    $config = $app->make('config');

    // Assuming that 'database' config sets a 'default' key
    expect($config->path('database.default'))->not()->toBeNull();
    // You can add more assertions to test different aspects of the configuration.
});

it('sets and gets base path correctly', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    $basePath = $app->basePath();

    expect($basePath)->toEqual($_ENV['APP_BASE_PATH']);
    // Add more assertions if you want to test setting a different base path.
});

it('determines if the application is running in the console', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    // Here you might need to mock PHP_SAPI to test both cli and non-cli scenarios
    $runningInConsole = $app->runningInConsole();

    // The expectation here depends on whether you're running tests in the console or not
    expect($runningInConsole)->toBe(true); // or false if not in console
});

it('registers configured providers', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);
    $app->configure('app');

    // You need to set up some providers in your config for this test to work
    $app->registerConfiguredProviders();

    // Assuming you have a ServiceProvider that binds a service named 'exampleService'
    $service = $app->make('log');
    expect($service)->toBeInstanceOf(Phare\Log\LogManager::class);
    // Replace ExpectedServiceProviderClass with the actual class you expect
});

it('checks the application environment', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    // Assume 'APP_ENV' is set to 'testing' for this scenario
    putenv('APP_ENV=testing');

    expect($app->environment('testing'))->toBe(true);
    expect($app->environment('production'))->toBe(false);

    // Clean up the environment variable after the test
    putenv('APP_ENV');
});

it('bootstrap the application with given bootstrappers', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    // Mock bootstrapper classes
    $bootstrappers = [
        Phare\Foundation\Bootstrap\HandleExceptions::class,
    ];

    $app->bootstrapWith($bootstrappers);

    // Verify that the app has been bootstrapped
    expect($app->hasBeenBootstrapped())->toBe(true);

    // Further assertions can be made to verify the effects of bootstrapping
});

it('determines if the application has been bootstrapped', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    expect($app->hasBeenBootstrapped())->toBe(false);

    // Perform bootstrapping then check again
    $app->bootstrapWith([Phare\Foundation\Bootstrap\HandleExceptions::class]);
    expect($app->hasBeenBootstrapped())->toBe(true);
});

it('terminates the application', function () {
    $app = new MockApplication($_ENV['APP_BASE_PATH']);

    // You can check if any resources need to be disposed of or if any final actions need to be taken
    $app->terminate();

    // Since terminate() might not return anything, you might want to check side effects
    // For instance, if terminate() should close database connections, check if that's the case
    // This might require a mock or a spy to check the underlying service state
    // expect($someService->isConnected())->toBe(false);
});
