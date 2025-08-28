<?php

namespace Phare\Notifications;

use Phare\Events\EventDispatcher;
use Phare\Notifications\Channels\ChannelManager;

class NotificationManager
{
    protected ChannelManager $channelManager;

    protected ?EventDispatcher $events;

    protected array $sentNotifications = [];

    public function __construct(ChannelManager $channelManager, ?EventDispatcher $events = null)
    {
        $this->channelManager = $channelManager;
        $this->events = $events;
    }

    /**
     * Send the given notification to the given notifiable entities.
     */
    public function send(mixed $notifiables, Notification $notification): void
    {
        if (!is_iterable($notifiables)) {
            $notifiables = [$notifiables];
        }

        foreach ($notifiables as $notifiable) {
            $this->sendToNotifiable($notifiable, $notification);
        }
    }

    /**
     * Send the given notification immediately to the given notifiable entities.
     */
    public function sendNow(mixed $notifiables, Notification $notification): void
    {
        // For now, sendNow is the same as send since we're not implementing queued notifications
        $this->send($notifiables, $notification);
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

        // Track sent notifications for testing purposes
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
     * Get the channel manager instance.
     */
    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    /**
     * Get sent notifications (for testing).
     */
    public function getSentNotifications(): array
    {
        return $this->sentNotifications;
    }

    /**
     * Clear sent notifications (for testing).
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
