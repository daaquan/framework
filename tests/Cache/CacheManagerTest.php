<?php

use InvalidArgumentException;
use Phalcon\Di\Di;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phare\Cache\CacheManager;

function refreshApplication(): void
{
    Di::reset();

    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    $app->bootstrapWith([
        \Phare\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Phare\Foundation\Bootstrap\LoadConfiguration::class,
        \Phare\Foundation\Bootstrap\HandleExceptions::class,
        \Phare\Foundation\Bootstrap\RegisterProviders::class,
        \Phare\Foundation\Bootstrap\RegisterFacades::class,
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
    $cacheDir = storage_path('framework/cache/data');
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
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
    expect($manager->adapter())->toBeInstanceOf(\Phalcon\Cache\Adapter\Apcu::class);
});
