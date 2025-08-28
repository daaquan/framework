<?php

use Phare\Broadcasting\Broadcasters\LogBroadcaster;
use Phare\Broadcasting\Channel;
use Psr\Log\LoggerInterface;

test('log broadcaster can authenticate', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $broadcaster = new LogBroadcaster($logger);

    $request = Mockery::mock('request');

    $result = $broadcaster->auth($request);

    expect($result)->toBeTrue();
});

test('log broadcaster returns valid authentication response', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $broadcaster = new LogBroadcaster($logger);

    $request = Mockery::mock('request');
    $result = $broadcaster->validAuthenticationResponse($request, true);

    expect($result)->toBe('true');
});

test('log broadcaster logs broadcast events', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->once()
        ->with('Broadcasting event', [
            'event' => 'test.event',
            'channels' => ['test-channel'],
            'payload' => ['data' => 'test'],
        ]);

    $broadcaster = new LogBroadcaster($logger);

    $broadcaster->broadcast(['test-channel'], 'test.event', ['data' => 'test']);
});

test('log broadcaster can broadcast to channel objects', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->once()
        ->with('Broadcasting event', [
            'event' => 'test.event',
            'channels' => ['test-channel'],
            'payload' => ['data' => 'test'],
        ]);

    $broadcaster = new LogBroadcaster($logger);
    $channel = new Channel('test-channel');

    $broadcaster->broadcast([$channel], 'test.event', ['data' => 'test']);
});
