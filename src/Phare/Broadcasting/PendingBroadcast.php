<?php

namespace Phare\Broadcasting;

use Phare\Events\Contracts\ShouldBroadcast;

class PendingBroadcast
{
    protected BroadcastManager $broadcaster;

    protected ShouldBroadcast $event;

    public function __construct(BroadcastManager $broadcaster, ShouldBroadcast $event)
    {
        $this->broadcaster = $broadcaster;
        $this->event = $event;
    }

    public function via(string|array $drivers): self
    {
        if (method_exists($this->event, 'broadcastVia')) {
            $this->event->broadcastVia = is_array($drivers) ? $drivers : [$drivers];
        }

        return $this;
    }

    public function toOthers(): self
    {
        if (method_exists($this->event, 'dontBroadcastToCurrentUser')) {
            $this->event->dontBroadcastToCurrentUser();
        }

        return $this;
    }

    public function __destruct()
    {
        $this->broadcaster->queue($this->event);
    }
}
