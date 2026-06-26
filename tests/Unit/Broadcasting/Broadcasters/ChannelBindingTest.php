<?php

use Phare\Broadcasting\Broadcasters\NullBroadcaster;

/** Mock request whose user() method returns a fixed user. */
function fakeRequest(?object $user = null)
{
    $request = Mockery::mock('request');
    $request->shouldReceive('user')->andReturn($user ?? (object)['id' => 42]);

    return $request;
}

test('exact channel name resolves and receives only the user', function () {
    $broadcaster = new NullBroadcaster();
    $seen = null;
    $broadcaster->channel('monitor', function ($user) use (&$seen) {
        $seen = $user;

        return true;
    });

    $result = $broadcaster->resolveBinding(fakeRequest(), 'presence-monitor');

    expect($result)->toBeTrue();
    expect($seen->id)->toBe(42);
});

test('wildcard {param} is extracted and passed after the user', function () {
    $broadcaster = new NullBroadcaster();
    $broadcaster->channel('App.User.{id}', function ($user, $id) {
        return (int)$user->id === (int)$id;
    });

    expect($broadcaster->resolveBinding(fakeRequest((object)['id' => 42]), 'private-App.User.42'))->toBeTrue();
    expect($broadcaster->resolveBinding(fakeRequest((object)['id' => 42]), 'private-App.User.99'))->toBeFalse();
});

test('multiple wildcards are passed in order', function () {
    $broadcaster = new NullBroadcaster();
    $broadcaster->channel('room.{room}.user.{uid}', function ($user, $room, $uid) {
        return [$room, $uid];
    });

    expect($broadcaster->resolveBinding(fakeRequest(), 'private-room.7.user.42'))->toBe(['7', '42']);
});

test('unregistered channel resolves to false', function () {
    $broadcaster = new NullBroadcaster();

    expect($broadcaster->resolveBinding(fakeRequest(), 'private-nope'))->toBeFalse();
});
