<?php

use Phare\Broadcasting\Channel;
use Phare\Broadcasting\PresenceChannel;
use Phare\Broadcasting\PrivateChannel;

test('channel can be created with name', function () {
    $channel = new Channel('test-channel');

    expect($channel->name)->toBe('test-channel');
});

test('channel can be converted to string', function () {
    $channel = new Channel('test-channel');

    expect((string)$channel)->toBe('test-channel');
});

test('private channel prefixes name with private', function () {
    $channel = new PrivateChannel('user-1');

    expect($channel->name)->toBe('private-user-1');
});

test('presence channel prefixes name with presence', function () {
    $channel = new PresenceChannel('chat-room');

    expect($channel->name)->toBe('presence-chat-room');
});
