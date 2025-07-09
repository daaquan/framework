<?php

use Phalcon\Storage\Exception as StorageException;
use Phalcon\Storage\SerializerFactory;
use Phare\Storage\Adapter\RedisCluster;

beforeEach(function () {
    $this->serializerFactory = Mockery::mock(SerializerFactory::class);

    $this->redisOptions = [
        'host' => '127.0.0.1',
        'port' => '7000',
        'persistent' => false,
    ];

    // Mock the RedisCluster class and its methods
    $this->redisClusterMock = Mockery::mock(\RedisCluster::class);
    $this->redisClusterMock->shouldReceive('setOption')->andReturn(true);
    $this->redisClusterMock->shouldReceive('connect')->andReturn(true);
});

test('getAdapter returns RedisCluster instance', function () {
    // Pass the mocked SerializerFactory to the RedisCluster constructor
    $redisClusterAdapter = new RedisCluster($this->serializerFactory, $this->redisOptions);
    $redisCluster = $redisClusterAdapter->getAdapter();

    // Assert that the getAdapter method returns an instance of RedisCluster
    expect($redisCluster)->toBeInstanceOf(\RedisCluster::class);
});

test('getAdapter throws exception on connection failure', function () {
    // Simulate connection failure by throwing an exception when connect is called
    $this->redisClusterMock->shouldReceive('connect')->andThrow(new \RedisClusterException('Connection failed'));

    $redisClusterAdapter = new RedisCluster($this->serializerFactory, $this->redisOptions + ['seeds' => []]);

    // Using Pest's higher order test to expect exception
    test()->expectException(StorageException::class);

    $redisClusterAdapter->getAdapter();
});

afterEach(function () {
    // This will check that the shouldReceive expectations set in the test were met
    Mockery::close();
});
