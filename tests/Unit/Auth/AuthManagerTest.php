<?php

declare(strict_types=1);

use Phalcon\Di\Di;
use Phare\Auth\AuthManager;
use Phare\Auth\Manager as SessionGuard;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;

function bootAuthManagerApplication(): void
{
    Di::reset();

    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterProviders::class,
        RegisterFacades::class,
    ]);

    Di::setDefault($app);
}

beforeEach(function () {
    bootAuthManagerApplication();
    $this->app = Di::getDefault();
});

test('default guard resolves to session driver', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Game\\User'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    expect($manager->guard())->toBeInstanceOf(SessionGuard::class);
    expect($manager->guard())->toBe($manager->guard()); // cached
});

test('guard(name) returns named guard', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'admin' => ['driver' => 'session', 'provider' => 'users', 'session_id' => 'auth.admin'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Game\\User'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    $web = $manager->guard('web');
    $admin = $manager->guard('admin');

    expect($web)->toBeInstanceOf(SessionGuard::class);
    expect($admin)->toBeInstanceOf(SessionGuard::class);
    expect($web)->not->toBe($admin);
});

test('driver(name) is a Laravel-parity alias for guard(name)', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Game\\User'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    expect($manager->driver('web'))->toBe($manager->guard('web'));
});

test('resolved guards are exposed through base manager driver cache', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Game\\User'],
        ],
    ]);

    $manager = new AuthManager($this->app);
    $first = $manager->guard('web');

    expect($manager->getDrivers())->toHaveKey('web');

    $manager->forgetDrivers();

    expect($manager->getDrivers())->toBe([])
        ->and($manager->guard('web'))->not->toBe($first);
});

test('undefined guard throws', function () {
    config(['auth.guards' => []]);

    $manager = new AuthManager($this->app);

    expect(fn () => $manager->guard('missing'))
        ->toThrow(InvalidArgumentException::class);
});

test('unsupported driver throws', function () {
    config([
        'auth.defaults.guard' => 'api',
        'auth.guards' => [
            'api' => ['driver' => 'jwt', 'provider' => 'users'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    expect(fn () => $manager->guard())
        ->toThrow(InvalidArgumentException::class);
});

test('extend(driver) registers a custom guard factory', function () {
    config([
        'auth.defaults.guard' => 'token',
        'auth.guards' => [
            'token' => ['driver' => 'token-stub'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    $captured = null;
    $manager->extend('token-stub', function ($app, $name, $cfg) use (&$captured) {
        $captured = [$name, $cfg];

        return new stdClass();
    });

    expect($manager->guard())->toBeInstanceOf(stdClass::class);
    expect($captured[0])->toBe('token');
    expect($captured[1])->toMatchArray(['driver' => 'token-stub']);
});

test('custom guard factory is bound to the auth manager', function () {
    config([
        'auth.defaults.guard' => 'token',
        'auth.guards' => [
            'token' => ['driver' => 'token-stub'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    $manager->extend('token-stub', function () {
        return (object)['boundToManager' => $this instanceof AuthManager];
    });

    expect($manager->guard()->boundToManager)->toBeTrue();
});

test('__call proxies to default guard', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Game\\User'],
        ],
    ]);

    $manager = new AuthManager($this->app);

    // Manager::guest() returns true when no user logged in.
    expect($manager->guest())->toBeTrue();
});

test('falls back to top-level auth.model when no provider mapping is set', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session'],
        ],
        'auth.providers' => null,
        'auth.model' => 'App\\Models\\Game\\User',
        'auth.session_id' => 'auth',
    ]);

    $manager = new AuthManager($this->app);

    expect($manager->guard())->toBeInstanceOf(SessionGuard::class);
});

test('legacy top-level auth config defines the default session guard', function () {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards' => [],
        'auth.model' => 'App\\Models\\Game\\User',
        'auth.session_id' => 'auth',
    ]);

    $manager = new AuthManager($this->app);

    expect($manager->guard())->toBeInstanceOf(SessionGuard::class);
    expect(fn () => $manager->guard('missing'))
        ->toThrow(InvalidArgumentException::class);
});
