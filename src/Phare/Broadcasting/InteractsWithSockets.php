<?php

namespace Phare\Broadcasting;

trait InteractsWithSockets
{
    public string $socket;

    public function dontBroadcastToCurrentUser(): self
    {
        $this->socket = request()->header('X-Socket-ID') ?? '';

        return $this;
    }

    public function toOthers(): self
    {
        return $this->dontBroadcastToCurrentUser();
    }
}
