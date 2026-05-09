<?php

use InvalidArgumentException;
use Phalcon\Cache\Adapter\Apcu;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phalcon\Di\Di;
use Phare\Cache\Adapter\ArrayAdapter;
use Phare\Cache\CacheManager;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;

function refreshApplication(): void
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
    // Ensure environment variable as default.
    putenv('CACHE_DRIVER');
    putenv('CACHE_DRIVER=file');

    refreshApplication();
});

test('default file cache driver uses stream adapter', function () {
    // ensure storage directory exists
    @mkdir(storage_path('framework/cache/data'), 0777, true);
    $manager = new CacheManager();
    expect($manager->adapter())->toBeInstanceOf(Stream::class);

    $manager->set('foo', 'bar');
    expect($manager->get('foo'))->toBe('bar');
    $manager->delete('foo');
    expect($manager->get('foo'))->toBeNull();
});

test('throws exception for invalid cache driver', function () {
    putenv('CACHE_DRIVER=invalid');
    refreshApplication();

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

test('throws exception when redis connection missing', function () {
    putenv('CACHE_DRIVER=redis');
    refreshApplication();
    config(['cache.stores.redis.connection' => 'missing']);

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

// verify redis adapter instantiation when connection config provided

test('redis cache driver uses redis adapter', function () {
    putenv('CACHE_DRIVER=redis');
    refreshApplication();

    // provide redis connection configuration expected by CacheManager
    config(['database.connections.redis' => [
        'default' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'persistent' => false,
        ],
    ]]);

    $manager = new CacheManager();
    expect($manager->adapter())->toBeInstanceOf(Redis::class);
});

// misconfigured file driver should throw exception when path missing

test('throws exception when file driver path missing', function () {
    putenv('CACHE_DRIVER=file');
    refreshApplication();

    config(['cache.stores.file.path' => null]);

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

// ensure apcu driver returns apcu adapter instance

test('apcu cache driver uses apcu adapter', function () {
    putenv('CACHE_DRIVER=apc');
    refreshApplication();

    $manager = new CacheManager();
    expect($manager->adapter())->toBeInstanceOf(Apcu::class);
});

test('store(name) resolves a non-default store and caches it', function () {
    putenv('CACHE_DRIVER=apc');
    refreshApplication();
    config(['cache.stores.array' => ['driver' => 'array', 'prefix' => 'mystore_']]);

    $manager = new CacheManager();
    $storeA = $manager->store('array');
    $storeB = $manager->store('array');

    expect($storeA)->toBeInstanceOf(ArrayAdapter::class);
    expect($storeA)->toBe($storeB); // cached, same instance
    expect($manager->adapter())->toBeInstanceOf(Apcu::class);
});

test('store(null) returns the default store', function () {
    putenv('CACHE_DRIVER=apc');
    refreshApplication();

    $manager = new CacheManager();

    expect($manager->store())->toBe($manager->adapter());
    expect($manager->store(null))->toBe($manager->adapter());
});

test('store(unknown) throws when configuration is missing', function () {
    putenv('CACHE_DRIVER=apc');
    refreshApplication();

    $manager = new CacheManager();

    expect(fn () => $manager->store('nope'))
        ->toThrow(InvalidArgumentException::class);
});
