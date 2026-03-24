<?php

use Phalcon\Mvc\Micro;
use Phare\Foundation\AbstractApplication;

class BootstrapLifecycleTestApplication extends AbstractApplication
{
    protected function createApplication()
    {
        $this->singleton('config', \Phalcon\Config\Config::class);

        return (new Micro())->notFound(static fn () => 'Not found');
    }

    public function handle($uri)
    {
        return $this->app->handle($uri);
    }

    public function terminate()
    {
        $this->app->stop();
    }
}

class BootstrapperWithBootstrapAndRegister
{
    public static array $calls = [];

    public function bootstrap(AbstractApplication $app): void
    {
        self::$calls[] = 'bootstrap:' . ($app->hasBeenBootstrapped() ? 'bootstrapped' : 'not-bootstrapped');
    }

    public function register(AbstractApplication $app): void
    {
        self::$calls[] = 'register';
    }
}

class LegacyRegisterBootstrapper
{
    public static array $calls = [];

    public function register(AbstractApplication $app): void
    {
        self::$calls[] = 'register:' . ($app->hasBeenBootstrapped() ? 'bootstrapped' : 'not-bootstrapped');
    }
}

class EmptyBootstrapper
{
}

beforeEach(function () {
    BootstrapperWithBootstrapAndRegister::$calls = [];
    LegacyRegisterBootstrapper::$calls = [];
});

it('prefers bootstrap when bootstrap and register are both available', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);

    $app->bootstrapWith([BootstrapperWithBootstrapAndRegister::class]);

    expect(BootstrapperWithBootstrapAndRegister::$calls)->toBe(['bootstrap:bootstrapped']);
});

it('falls back to register for legacy bootstrappers', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);

    $app->bootstrapWith([LegacyRegisterBootstrapper::class]);

    expect(LegacyRegisterBootstrapper::$calls)->toBe(['register:bootstrapped']);
});

it('runs before and after bootstrap callbacks around each bootstrapper', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);
    $events = [];

    $app->beforeBootstrapping(LegacyRegisterBootstrapper::class, function ($application) use (&$events) {
        $events[] = 'before:' . ($application->hasBeenBootstrapped() ? 'bootstrapped' : 'not-bootstrapped');
    });

    $app->afterBootstrapping(LegacyRegisterBootstrapper::class, function ($application) use (&$events) {
        $events[] = 'after:' . ($application->hasBeenBootstrapped() ? 'bootstrapped' : 'not-bootstrapped');
    });

    $app->bootstrapWith([LegacyRegisterBootstrapper::class]);

    expect($events)->toBe([
        'before:bootstrapped',
        'after:bootstrapped',
    ]);
});

it('throws when bootstrapper does not define bootstrap or register', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);

    expect(fn () => $app->bootstrapWith([EmptyBootstrapper::class]))
        ->toThrow(RuntimeException::class, 'must define bootstrap(Application) or register(Application)');
});
