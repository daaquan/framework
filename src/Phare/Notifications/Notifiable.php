<?php

namespace Phare\Notifications;

trait Notifiable
{
    /**
     * Get the entity's notifications.
     */
    public function notifications(): array
    {
        // In a real implementation, this would return notifications from the database
        return [];
    }

    /**
     * Get the entity's read notifications.
     */
    public function readNotifications(): array
    {
        return array_filter($this->notifications(), function ($notification) {
            return $notification->isRead();
        });
    }

    /**
     * Get the entity's unread notifications.
     */
    public function unreadNotifications(): array
    {
        return array_filter($this->notifications(), function ($notification) {
            return !$notification->isRead();
        });
    }

    /**
     * Send the given notification.
     */
    public function notify(Notification $notification): void
    {
        app('notification')->send($this, $notification);
    }

    /**
     * Send the given notification immediately.
     */
    public function notifyNow(Notification $notification): void
    {
        app('notification')->sendNow($this, $notification);
    }

    /**
     * Get the notification routing information for the given driver.
     */
    public function routeNotificationFor(string $driver): mixed
    {
        $method = 'routeNotificationFor' . ucfirst($driver);

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return null;
    }

    /**
     * Get the email address for notifications.
     */
    public function routeNotificationForMail(): ?string
    {
        if (method_exists($this, 'getEmailForNotifications')) {
            return $this->getEmailForNotifications();
        }

        return $this->email ?? null;
    }

    /**
     * Get the phone number for SMS notifications.
     */
    public function routeNotificationForSms(): ?string
    {
        if (method_exists($this, 'getPhoneForNotifications')) {
            return $this->getPhoneForNotifications();
        }

        return $this->phone ?? null;
    }

    /**
     * Get the Slack webhook URL for notifications.
     */
    public function routeNotificationForSlack(): ?string
    {
        if (method_exists($this, 'getSlackWebhookForNotifications')) {
            return $this->getSlackWebhookForNotifications();
        }

        return $this->slack_webhook ?? null;
    }
}