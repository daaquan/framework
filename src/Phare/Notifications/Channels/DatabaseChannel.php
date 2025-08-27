<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Notification;

class DatabaseChannel implements ChannelInterface
{
    protected array $storedNotifications = [];

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $data = $this->buildPayload($notifiable, $notification);

        // In a real implementation, this would save to database
        // For now, we'll store in memory for testing
        $this->storedNotifications[] = $data;
    }

    /**
     * Build the notification payload.
     */
    protected function buildPayload(mixed $notifiable, Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $this->getNotifiableId($notifiable),
            'data' => $notification->toDatabase($notifiable),
            'read_at' => $notification->getReadAt()?->format('Y-m-d H:i:s'),
            'created_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get the notifiable entity's ID.
     */
    protected function getNotifiableId(mixed $notifiable): mixed
    {
        if (method_exists($notifiable, 'getKey')) {
            return $notifiable->getKey();
        }

        if (method_exists($notifiable, 'getId')) {
            return $notifiable->getId();
        }

        if (isset($notifiable->id)) {
            return $notifiable->id;
        }

        return null;
    }

    /**
     * Get stored notifications (for testing).
     */
    public function getStoredNotifications(): array
    {
        return $this->storedNotifications;
    }

    /**
     * Clear stored notifications (for testing).
     */
    public function clearStoredNotifications(): void
    {
        $this->storedNotifications = [];
    }
}