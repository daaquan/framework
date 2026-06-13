<?php

namespace Phare\Notifications;

use Phare\Contracts\Queue\ShouldQueue;
use Phare\Events\Contracts\Dispatcher as EventDispatcher;
use Phare\Notifications\Channels\ChannelManager;
use Phare\Queue\Job;
use Phare\Queue\QueueInterface;

class NotificationManager
{
    protected ChannelManager $channelManager;

    protected ?EventDispatcher $events;

    /**
     * When true, dispatched notifications are recorded in memory for later
     * inspection. This is intended for tests and is gated rather than
     * always-on so production usage does not accumulate an unbounded array.
     */
    protected bool $recording;

    protected array $sentNotifications = [];

    public function __construct(
        ChannelManager $channelManager,
        ?EventDispatcher $events = null,
        ?bool $recording = null
    ) {
        $this->channelManager = $channelManager;
        $this->events = $events;
        // Recording defaults on for backward compatibility; pass false in
        // long-running processes to avoid accumulating an unbounded array.
        $this->recording = $recording ?? true;
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * Notifications implementing ShouldQueue are pushed onto the queue instead
     * of being delivered inline.
     */
    public function send(mixed $notifiables, Notification $notification): void
    {
        if ($notification instanceof ShouldQueue) {
            $this->queueNotification($notifiables, $notification);

            return;
        }

        $this->sendNow($notifiables, $notification);
    }

    /**
     * Send the given notification immediately, bypassing the queue.
     */
    public function sendNow(mixed $notifiables, Notification $notification): void
    {
        foreach ($this->normalizeNotifiables($notifiables) as $notifiable) {
            $this->sendToNotifiable($notifiable, $notification);
        }
    }

    /**
     * Push a queueable notification onto the queue. Falls back to inline
     * delivery only when no queue connection can be resolved.
     */
    protected function queueNotification(mixed $notifiables, Notification $notification): void
    {
        $queue = $this->resolveQueue();

        if ($queue === null) {
            // No queue is available; deliver inline rather than dropping the
            // notification.
            $this->sendNow($notifiables, $notification);

            return;
        }

        foreach ($this->normalizeNotifiables($notifiables) as $notifiable) {
            $queue->push(new SendQueuedNotification($notifiable, $notification));
        }
    }

    /**
     * Normalize a single notifiable or an iterable into an iterable.
     */
    protected function normalizeNotifiables(mixed $notifiables): iterable
    {
        if (!is_iterable($notifiables)) {
            return [$notifiables];
        }

        return $notifiables;
    }

    /**
     * Send a notification to a single notifiable entity.
     */
    protected function sendToNotifiable(mixed $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        if (empty($channels)) {
            return;
        }

        $this->dispatchEvent('notification.sending', [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'channels' => $channels,
        ]);

        foreach ($channels as $channel) {
            if (!$notification->shouldSend($notifiable, $channel)) {
                continue;
            }

            try {
                $this->sendViaChannel($notifiable, $notification, $channel);
            } catch (\Exception $e) {
                $this->dispatchEvent('notification.failed', [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                    'channel' => $channel,
                    'error' => $e,
                ]);

                // Re-throw the exception if not handling it
                throw $e;
            }
        }

        $this->dispatchEvent('notification.sent', [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'channels' => $channels,
        ]);

        $this->recordSent($notifiable, $notification, $channels);
    }

    /**
     * Record a sent notification when recording is enabled.
     */
    protected function recordSent(mixed $notifiable, Notification $notification, array $channels): void
    {
        if (!$this->recording) {
            return;
        }

        $this->sentNotifications[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'channels' => $channels,
            'sent_at' => new \DateTime(),
        ];
    }

    /**
     * Send the notification via the given channel.
     */
    protected function sendViaChannel(mixed $notifiable, Notification $notification, string $channel): void
    {
        $driver = $this->channelManager->driver($channel);
        $driver->send($notifiable, $notification);
    }

    /**
     * Resolve the queue connection from the container, if available.
     */
    protected function resolveQueue(): ?QueueInterface
    {
        if (!function_exists('app') || !$this->hasApplication()) {
            return null;
        }

        try {
            $queue = app('queue');
        } catch (\Throwable) {
            return null;
        }

        if ($queue instanceof QueueInterface) {
            return $queue;
        }

        // The 'queue' binding resolves to a QueueManager; ask it for the
        // default connection.
        if (is_object($queue) && method_exists($queue, 'connection')) {
            try {
                $connection = $queue->connection();
            } catch (\Throwable) {
                return null;
            }

            return $connection instanceof QueueInterface ? $connection : null;
        }

        return null;
    }

    /**
     * Determine whether an application container has been bootstrapped.
     */
    protected function hasApplication(): bool
    {
        return function_exists('app') && app() !== null;
    }

    /**
     * Get the channel manager instance.
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get sent notifications (recording mode, for testing).
     */
    public function getSentNotifications(): array
    {
        return $this->sentNotifications;
    }

    /**
     * Clear sent notifications (recording mode, for testing).
     */
    public function clearSentNotifications(): void
    {
        $this->sentNotifications = [];
    }

    /**
     * Dispatch an event if the event dispatcher is available.
     */
    protected function dispatchEvent(string $event, array $data): void
    {
        if ($this->events) {
            $this->events->dispatch($event, $data);
        }
    }

    /**
     * Create a new notification channel driver.
     */
    public function channel(string $channel): mixed
    {
        return $this->channelManager->driver($channel);
    }

    /**
     * Get the default channel driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->channelManager->getDefaultDriver();
    }

    /**
     * Set the default channel driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->channelManager->setDefaultDriver($name);
    }
}

/**
 * Queue job that delivers a notification inline when processed by a worker.
 *
 * Defined alongside the manager so the queued notification path has a concrete
 * Job to push without requiring a separate autoloadable class.
 */
class SendQueuedNotification extends Job
{
    public function __construct(
        protected mixed $notifiable,
        protected Notification $notification
    ) {
        parent::__construct();

        $this->withData([
            'notification' => get_class($notification),
            'notification_id' => $notification->getId(),
        ]);
    }

    /**
     * Deliver the notification inline.
     */
    public function handle(): void
    {
        if (!function_exists('app')) {
            return;
        }

        $manager = app(NotificationManager::class);

        if ($manager instanceof NotificationManager) {
            $manager->sendNow($this->notifiable, $this->notification);
        }
    }

    /**
     * Get the notifiable entity this job will deliver to.
     */
    public function getNotifiable(): mixed
    {
        return $this->notifiable;
    }

    /**
     * Get the notification this job will deliver.
     */
    public function getNotification(): Notification
    {
        return $this->notification;
    }
}
