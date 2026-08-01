<?php

use Phalcon\Config\Config;
use Phalcon\Mvc\Micro;
use Phare\Foundation\AbstractApplication;

class BootstrapLifecycleTestApplication extends AbstractApplication
{
    protected function createApplication()
    {
        $this->singleton('config', Config::class);

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

class EmptyBootstrapper {}

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

it('supports custom environment resolver and environment path helpers', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);

    $resolved = $app->detectEnvironment(fn () => 'staging');

    expect($resolved)->toBe('staging');
    expect($app->environment())->toBe('staging');
    expect($app->environment('staging'))->toBeTrue();

    $app->useEnvironmentPath('/tmp/phare-env')->loadEnvironmentFrom('.env.staging');

    expect($app->environmentPath())->toBe('/tmp/phare-env');
    expect($app->environmentFile())->toBe('.env.staging');
    expect($app->environmentFilePath())->toBe('/tmp/phare-env/.env.staging');
});

it('exposes events cache helpers and terminating callbacks', function () {
    $app = new BootstrapLifecycleTestApplication($_ENV['APP_BASE_PATH']);
    $called = false;

    $app->terminating(function () use (&$called) {
        $called = true;
    });

    expect($app->eventsAreCached())->toBeFalse();
    expect($app->getCachedEventsPath())->toEndWith('bootstrap/cache/events.php');

    $app->callTerminatingCallbacks();

    expect($called)->toBeTrue();
});
