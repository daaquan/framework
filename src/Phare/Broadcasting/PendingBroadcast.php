<?php

namespace Phare\Broadcasting;

use Phare\Events\Contracts\ShouldBroadcast;

class PendingBroadcast
{
    protected BroadcastManager $broadcaster;

    protected ShouldBroadcast $event;

    /**
     * Whether the broadcast has already been handed to the manager. Guards the
     * auto-dispatch in {@see __destruct()} so an explicit {@see send()} does not
     * fire the broadcast a second time when the object is later collected.
     */
    protected bool $dispatched = false;

    public function __construct(BroadcastManager $broadcaster, ShouldBroadcast $event)
    {
        $this->broadcaster = $broadcaster;
        $this->event = $event;
    }

    public function via(string|array $drivers): self
    {
        // Prefer the typed setter so the override is read back by
        // BroadcastEvent::broadcastVia(); fall back to a dynamic property for
        // events that don't extend BroadcastEvent but expose broadcastVia().
        if (method_exists($this->event, 'setBroadcastVia')) {
            $this->event->setBroadcastVia($drivers);
        } elseif (method_exists($this->event, 'broadcastVia')) {
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

    /**
     * Dispatch the broadcast explicitly. Errors surface to the caller (unlike
     * the destructor path) and the broadcast is marked as dispatched so it is
     * not re-fired when the object is destroyed.
     */
    public function send(): void
    {
        $this->dispatched = true;

        $this->broadcaster->queue($this->event);
    }

    /**
     * Alias for {@see send()} to match the dispatch-oriented vocabulary used by
     * the events layer.
     */
    public function dispatch(): void
    {
        $this->send();
    }

    public function __destruct()
    {
        if ($this->dispatched) {
            return;
        }

        $this->dispatched = true;

        // Auto-dispatch convenience: swallow errors here because destructors
        // must not throw. Use send()/dispatch() for error-surfacing dispatch.
        try {
            $this->broadcaster->queue($this->event);
        } catch (\Throwable) {
            // Intentionally ignored — see send() for the explicit path.
        }
    }
}
