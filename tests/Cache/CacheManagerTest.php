<?php

use InvalidArgumentException;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phare\Cache\CacheManager;
use Tests\TestCase;

uses(TestCase::class)->beforeEach(function () {
    // Ensure environment variable as default
    putenv('CACHE_DRIVER');
    putenv('CACHE_DRIVER=file');
    $this->setUpApplication();
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
    $this->setUpApplication();

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

test('throws exception when redis connection missing', function () {
    putenv('CACHE_DRIVER=redis');
    $this->setUpApplication();

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

// verify redis adapter instantiation when connection config provided

test('redis cache driver uses redis adapter', function () {
    putenv('CACHE_DRIVER=redis');
    $this->setUpApplication();

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
    $this->setUpApplication();

    config(['cache.stores.file.path' => null]);

    expect(fn () => new CacheManager())
        ->toThrow(InvalidArgumentException::class);
});

// ensure apcu driver returns apcu adapter instance

test('apcu cache driver uses apcu adapter', function () {
    putenv('CACHE_DRIVER=apc');
    $this->setUpApplication();

    $manager = new CacheManager();
    expect($manager->adapter())->toBeInstanceOf(\Phalcon\Cache\Adapter\Apcu::class);
});
