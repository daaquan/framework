<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Notification;
use Phare\Notifications\Messages\SlackMessage;

class SlackChannel implements ChannelInterface
{
    protected array $sentMessages = [];

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $message = $notification->toSlack($notifiable);

        if (!$message instanceof SlackMessage) {
            return;
        }

        $webhook = $this->getWebhook($notifiable, $message);

        if (empty($webhook)) {
            return;
        }

        // For testing purposes, we'll store the message instead of actually sending
        $this->sentMessages[] = [
            'webhook' => $webhook,
            'channel' => $message->getChannel(),
            'text' => $message->getText(),
            'attachments' => $message->getAttachments(),
            'sent_at' => new \DateTime()
        ];
    }

    /**
     * Get the webhook URL for the message.
     */
    protected function getWebhook(mixed $notifiable, SlackMessage $message): string
    {
        if ($message->hasWebhook()) {
            return $message->getWebhook();
        }

        if (method_exists($notifiable, 'routeNotificationForSlack')) {
            return $notifiable->routeNotificationForSlack();
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