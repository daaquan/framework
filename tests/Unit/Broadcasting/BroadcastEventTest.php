<?php

use Phare\Broadcasting\BroadcastEvent;
use Phare\Broadcasting\Channel;
use Phare\Broadcasting\PrivateChannel;

class TestBroadcastEvent extends BroadcastEvent
{
    public function broadcastOn(): array
    {
        return [
            new Channel('test-channel'),
            new PrivateChannel('private-channel'),
        ];
    }

    public function broadcastAs(): ?string
    {
        return 'test.event';
    }

    public function broadcastWith(): array
    {
        return ['message' => 'Hello World'];
    }
}

test('broadcast event returns channels', function () {
    $event = new TestBroadcastEvent();
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0])->toBeInstanceOf(Channel::class);
    expect($channels[1])->toBeInstanceOf(PrivateChannel::class);
});

test('broadcast event returns custom event name', function () {
    $event = new TestBroadcastEvent();

    expect($event->broadcastAs())->toBe('test.event');
});

test('broadcast event returns data', function () {
    $event = new TestBroadcastEvent();

    expect($event->broadcastWith())->toBe(['message' => 'Hello World']);
});

test('broadcast event defaults', function () {
    $event = new TestBroadcastEvent();

    expect($event->broadcastWhen())->toBeTrue();
    expect($event->broadcastQueue())->toBeNull();
    expect($event->broadcastConnection())->toBeNull();
    expect($event->broadcastVia())->toBe(['pusher']);
});

test('broadcast event can set socket for exclusion', function () {
    $event = new TestBroadcastEvent();

    $result = $event->dontBroadcastToCurrentUser();

    expect($result)->toBe($event);
    expect(isset($event->socket))->toBeTrue();
});

test('broadcast event to others is alias for dont broadcast to current user', function () {
    $event = new TestBroadcastEvent();

    $result = $event->toOthers();

    expect($result)->toBe($event);
    expect(isset($event->socket))->toBeTrue();
});
