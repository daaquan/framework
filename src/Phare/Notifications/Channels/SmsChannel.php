<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Messages\SmsMessage;
use Phare\Notifications\Notification;

class SmsChannel implements ChannelInterface
{
    protected array $sentMessages = [];

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $message = $notification->toSms($notifiable);

        if (!$message instanceof SmsMessage) {
            return;
        }

        $to = $this->getRecipients($notifiable, $message);

        if (empty($to)) {
            return;
        }

        // For testing purposes, we'll store the message instead of actually sending
        $this->sentMessages[] = [
            'to' => $to,
            'message' => $message->getContent(),
            'from' => $message->getFrom(),
            'sent_at' => new \DateTime(),
        ];
    }

    /**
     * Get the recipients for the message.
     */
    protected function getRecipients(mixed $notifiable, SmsMessage $message): string
    {
        if ($message->hasTo()) {
            return $message->getTo();
        }

        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms();
        }

        if (method_exists($notifiable, 'getPhoneForNotifications')) {
            return $notifiable->getPhoneForNotifications();
        }

        if (isset($notifiable->phone)) {
            return $notifiable->phone;
        }

        return '';
    }

    /**
     * Get sent messages (for testing).
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Clear sent messages (for testing).
     */
    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
    }
}
