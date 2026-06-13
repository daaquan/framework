<?php

namespace Phare\Broadcasting;

use Phare\Events\Contracts\ShouldBroadcast;

abstract class BroadcastEvent implements ShouldBroadcast
{
    public string $socket;

    /**
     * Connection override set by {@see PendingBroadcast::via()}. When non-null
     * it takes precedence over the default {@see broadcastVia()} channels so
     * `->via('redis')` actually re-routes the broadcast.
     *
     * @var array<int, string>|null
     */
    protected ?array $broadcastViaOverride = null;

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
        return $this->broadcastViaOverride ?? ['pusher'];
    }

    /**
     * Override the broadcast connection(s) for this event instance. Honoured by
     * {@see broadcastVia()}. Passing null restores the default channels.
     *
     * @param array<int, string>|string|null $drivers
     */
    public function setBroadcastVia(array|string|null $drivers): self
    {
        if ($drivers === null) {
            $this->broadcastViaOverride = null;
        } else {
            $this->broadcastViaOverride = is_array($drivers) ? array_values($drivers) : [$drivers];
        }

        return $this;
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
