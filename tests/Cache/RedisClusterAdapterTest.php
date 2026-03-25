<?php

use Phalcon\Storage\Exception as StorageException;
use Phalcon\Storage\SerializerFactory;
use Phare\Storage\Adapter\RedisCluster;

test('getAdapter returns existing RedisCluster instance when already connected', function () {
    $adapter = new RedisCluster(new SerializerFactory(), ['host' => '127.0.0.1', 'port' => '7000']);
    $existing = (new ReflectionClass(\RedisCluster::class))->newInstanceWithoutConstructor();

    $property = new ReflectionProperty(\Phalcon\Storage\Adapter\Redis::class, 'adapter');
    $property->setValue($adapter, $existing);

    expect($adapter->getAdapter())->toBe($existing);
});

test('getAdapter wraps connection failures in StorageException', function () {
    $adapter = new RedisCluster(new SerializerFactory(), [
        'seeds' => ['127.0.0.1:7000'],
        'timeout' => 0.05,
        'readTimeout' => 0.05,
        'auth' => null,
    ]);

    expect(fn () => $adapter->getAdapter())
        ->toThrow(StorageException::class, 'Failed to connect to the Redis cluster');
});
