<?php

namespace Phare\Broadcasting;

use Phare\Events\Contracts\ShouldBroadcast;

abstract class BroadcastEvent implements ShouldBroadcast
{
    public string $socket;

    public function broadcastOn(): array
    {
        return [];
    }

    public function broadcastAs(): ?string
    {
        return null;
    }

    public function broadcastWith(): array
    {
        return [];
    }

    public function broadcastWhen(): bool
    {
        return true;
    }

    public function broadcastQueue(): ?string
    {
        return null;
    }

    public function broadcastConnection(): ?string
    {
        return null;
    }

    public function broadcastVia(): array
    {
        return ['pusher'];
    }

    public function dontBroadcastToCurrentUser(): self
    {
        $request = request();
        $this->socket = $request ? $request->header('X-Socket-ID') ?? '' : '';

        return $this;
    }

    public function toOthers(): self
    {
        return $this->dontBroadcastToCurrentUser();
    }
}
