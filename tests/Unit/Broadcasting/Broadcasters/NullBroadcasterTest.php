<?php

use Phare\Broadcasting\Broadcasters\NullBroadcaster;

test('null broadcaster can authenticate', function () {
    $broadcaster = new NullBroadcaster();
    $request = Mockery::mock('request');

    $result = $broadcaster->auth($request);

    expect($result)->toBeTrue();
});

test('null broadcaster returns valid authentication response', function () {
    $broadcaster = new NullBroadcaster();
    $request = Mockery::mock('request');

    $result = $broadcaster->validAuthenticationResponse($request, true);

    expect($result)->toBe('true');
});

test('null broadcaster does nothing on broadcast', function () {
    $broadcaster = new NullBroadcaster();

    // This should not throw any exceptions
    $result = $broadcaster->broadcast(['test-channel'], 'test.event', ['data' => 'test']);

    expect($result)->toBeNull();
});
