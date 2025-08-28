<?php

namespace Phare\Broadcasting\Broadcasters;

class NullBroadcaster extends Broadcaster
{
    public function auth(mixed $request): mixed
    {
        return true;
    }

    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        return json_encode($result);
    }

    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        // Do nothing
    }
}
