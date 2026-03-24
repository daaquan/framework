<?php

use Phalcon\Di\Di;
use Phare\Cache\CacheManager;
use Phare\Foundation\Cache;
use Phare\Providers\CacheProvider;

function refreshCacheRepositoryTestApplication(): void
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
    putenv('CACHE_DRIVER');
    putenv('CACHE_DRIVER=file');

    refreshCacheRepositoryTestApplication();
    @mkdir(storage_path('framework/cache/data'), 0777, true);
    (new CacheProvider())->register(app());
});

it('provides laravel-like repository operations', function () {
    $cache = app('cache');

    expect($cache)->toBeInstanceOf(Cache::class);
    expect($cache->put('name', 'phare'))->toBeTrue();
    expect($cache->get('name'))->toBe('phare');
    expect($cache->add('name', 'laravel'))->toBeFalse();
    expect($cache->pull('name'))->toBe('phare');
    expect($cache->get('name'))->toBeNull();

    $hits = 0;
    $value = $cache->remember('remember-key', 60, function () use (&$hits) {
        $hits++;

        return 'cached-value';
    });
    expect($value)->toBe('cached-value');
    expect($cache->remember('remember-key', 60, function () use (&$hits) {
        $hits++;

        return 'new-value';
    }))->toBe('cached-value');
    expect($hits)->toBe(1);

    expect($cache->rememberForever('forever-key', fn () => 'forever-value'))->toBe('forever-value');
    expect($cache->sear('sear-key', fn () => 'sear-value'))->toBe('sear-value');
    expect($cache->setMultiple(['k1' => 'v1', 'k2' => 'v2']))->toBeTrue();
    expect($cache->getMultiple(['k1', 'k2', 'k3'], 'default'))
        ->toBe(['k1' => 'v1', 'k2' => 'v2', 'k3' => 'default']);
    expect($cache->deleteMultiple(['k1', 'k2']))->toBeTrue();
    expect($cache->forget('remember-key'))->toBeTrue();
    expect($cache->flush())->toBeTrue();
});

it('expires values based on ttl', function () {
    $cache = app('cache');

    expect($cache->put('short', 'life', 1))->toBeTrue();
    expect($cache->get('short'))->toBe('life');

    sleep(2);

    expect($cache->get('short'))->toBeNull();
});

it('supports array cache driver via cache manager', function () {
    putenv('CACHE_DRIVER');
    putenv('CACHE_DRIVER=file');
    refreshCacheRepositoryTestApplication();
    config(['cache.default' => 'array']);

    $manager = new CacheManager();
    expect($manager->set('array-key', 'array-value'))->toBeTrue();
    expect($manager->get('array-key'))->toBe('array-value');
    expect($manager->set('expiring-array', 'soon-gone', 1))->toBeTrue();

    sleep(2);

    expect($manager->get('expiring-array'))->toBeNull();
});

it('supports null cache driver via cache manager', function () {
    putenv('CACHE_DRIVER');
    putenv('CACHE_DRIVER=file');
    refreshCacheRepositoryTestApplication();
    config(['cache.default' => 'null']);
    config(['cache.stores.null' => ['driver' => 'null']]);

    $manager = new CacheManager();
    expect($manager->set('null-key', 'value'))->toBeFalse();
    expect($manager->get('null-key', 'default'))->toBe('default');
    expect($manager->clear())->toBeTrue();
});
