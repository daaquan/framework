<?php

use Phare\Broadcasting\Broadcasters\LogBroadcaster;
use Phare\Broadcasting\Broadcasters\NullBroadcaster;
use Phare\Broadcasting\BroadcastManager;
use Phare\Config\Repository as Config;
use Phare\Container\Container;

test('broadcast manager can get default driver', function () {
    $container = new Container();
    $config = new Config(['broadcasting.default' => 'null']);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);

    expect($manager->getDefaultDriver())->toBe('null');
});

test('broadcast manager can create null driver', function () {
    $container = new Container();
    $config = new Config([
        'broadcasting.default' => 'null',
        'broadcasting.connections.null' => ['driver' => 'null'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);
    $broadcaster = $manager->driver('null');

    expect($broadcaster)->toBeInstanceOf(NullBroadcaster::class);
});

test('broadcast manager can create log driver', function () {
    $container = new Container();
    $logger = Mockery::mock('Psr\Log\LoggerInterface');
    $container['log'] = $logger;

    $config = new Config([
        'broadcasting.default' => 'log',
        'broadcasting.connections.log' => ['driver' => 'log'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);
    $broadcaster = $manager->driver('log');

    expect($broadcaster)->toBeInstanceOf(LogBroadcaster::class);
});

test('broadcast manager can extend with custom driver', function () {
    $container = new Container();
    $config = new Config([
        'broadcasting.default' => 'custom',
        'broadcasting.connections.custom' => ['driver' => 'custom'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);
    $manager->extend('custom', function ($container, $config) {
        return new NullBroadcaster();
    });

    $broadcaster = $manager->driver('custom');

    expect($broadcaster)->toBeInstanceOf(NullBroadcaster::class);
});

test('broadcast manager can set default driver', function () {
    $container = new Container();
    $config = new Config(['broadcasting.default' => 'null']);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);
    $manager->setDefaultDriver('log');

    expect($manager->getDefaultDriver())->toBe('log');
});

test('broadcast manager throws exception for invalid driver', function () {
    $container = new Container();
    $config = new Config([
        'broadcasting.connections.invalid' => ['driver' => 'invalid'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);

    expect(fn () => $manager->driver('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Driver [invalid] is not supported.');
});

test('broadcast manager throws exception for missing connection', function () {
    $container = new Container();
    $config = new Config([]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);

    expect(fn () => $manager->driver('missing'))
        ->toThrow(InvalidArgumentException::class, 'Broadcasting connection [missing] not configured.');
});

test('broadcast manager can purge driver', function () {
    $container = new Container();
    $config = new Config([
        'broadcasting.default' => 'null',
        'broadcasting.connections.null' => ['driver' => 'null'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);
    $broadcaster1 = $manager->driver('null');

    $manager->purge('null');
    $broadcaster2 = $manager->driver('null');

    expect($broadcaster1)->not->toBe($broadcaster2);
});

test('broadcast manager delegates calls to driver', function () {
    $container = new Container();
    $config = new Config([
        'broadcasting.default' => 'null',
        'broadcasting.connections.null' => ['driver' => 'null'],
    ]);
    $container['config'] = $config;

    $manager = new BroadcastManager($container);

    // This should not throw an exception
    $result = $manager->broadcast(['test'], 'event', []);

    expect($result)->toBeNull();
});
