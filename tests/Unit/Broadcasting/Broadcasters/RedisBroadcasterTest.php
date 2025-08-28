<?php

use Phare\Broadcasting\Broadcasters\RedisBroadcaster;
use Phare\Broadcasting\Channel;

test('redis broadcaster can authenticate public channels', function () {
    $redis = Mockery::mock('redis');
    $broadcaster = new RedisBroadcaster($redis);

    $request = Mockery::mock('request');
    $request->shouldReceive('get')
        ->with('channel_name')
        ->andReturn('public-channel');

    $result = $broadcaster->auth($request);

    expect($result)->toBeTrue();
});

test('redis broadcaster validates private channels', function () {
    $redis = Mockery::mock('redis');
    $broadcaster = Mockery::mock(RedisBroadcaster::class, [$redis])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $request = Mockery::mock('request');
    $request->shouldReceive('get')
        ->with('channel_name')
        ->andReturn('private-channel');

    $broadcaster->shouldReceive('verifyUserCanAccessChannel')
        ->once()
        ->with($request, 'channel')
        ->andReturn(true);

    $result = $broadcaster->auth($request);

    expect($result)->toBeTrue();
});

test('redis broadcaster returns valid authentication response for boolean', function () {
    $redis = Mockery::mock('redis');
    $broadcaster = new RedisBroadcaster($redis);

    $request = Mockery::mock('request');

    $result = $broadcaster->validAuthenticationResponse($request, true);

    expect($result)->toBe('true');
});

test('redis broadcaster returns channel data for user info', function () {
    $redis = Mockery::mock('redis');
    $broadcaster = new RedisBroadcaster($redis);

    $user = Mockery::mock('user');
    $user->id = 1;

    $request = Mockery::mock('request');
    $request->shouldReceive('user')
        ->andReturn($user);

    $result = $broadcaster->validAuthenticationResponse($request, ['name' => 'John']);
    $decoded = json_decode($result, true);

    expect($decoded)->toHaveKey('channel_data');
    expect($decoded['channel_data'])->toHaveKey('user_id', 1);
    expect($decoded['channel_data'])->toHaveKey('user_info', ['name' => 'John']);
});

test('redis broadcaster can broadcast to channels', function () {
    $connection = Mockery::mock('Redis');
    $connection->shouldReceive('publish')
        ->once()
        ->with('test-channel', json_encode([
            'event' => 'test.event',
            'data' => ['message' => 'hello'],
            'socket' => null,
        ]));

    $redis = Mockery::mock('redis');
    $redis->shouldReceive('connection')
        ->with('default')
        ->andReturn($connection);

    $broadcaster = new RedisBroadcaster($redis);

    $broadcaster->broadcast(['test-channel'], 'test.event', ['message' => 'hello']);
});

test('redis broadcaster can broadcast with socket exclusion', function () {
    $connection = Mockery::mock('Redis');
    $connection->shouldReceive('publish')
        ->once()
        ->with('test-channel', json_encode([
            'event' => 'test.event',
            'data' => ['message' => 'hello', 'socket' => 'socket-id'],
            'socket' => 'socket-id',
        ]));

    $redis = Mockery::mock('redis');
    $redis->shouldReceive('connection')
        ->with('default')
        ->andReturn($connection);

    $broadcaster = new RedisBroadcaster($redis);

    $broadcaster->broadcast(['test-channel'], 'test.event', ['message' => 'hello', 'socket' => 'socket-id']);
});

test('redis broadcaster can broadcast to channel objects', function () {
    $connection = Mockery::mock('Redis');
    $connection->shouldReceive('publish')
        ->once()
        ->with('test-channel', Mockery::type('string'));

    $redis = Mockery::mock('redis');
    $redis->shouldReceive('connection')
        ->with('default')
        ->andReturn($connection);

    $broadcaster = new RedisBroadcaster($redis);
    $channel = new Channel('test-channel');

    $broadcaster->broadcast([$channel], 'test.event', ['message' => 'hello']);
});
