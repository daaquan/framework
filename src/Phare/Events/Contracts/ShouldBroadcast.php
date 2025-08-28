<?php

namespace Phare\Events\Contracts;

interface ShouldBroadcast
{
    public function broadcastOn(): array;

    public function broadcastAs(): ?string;

    public function broadcastWith(): array;

    public function broadcastWhen(): bool;
}
