<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Messages\SlackMessage;
use Phare\Notifications\Notification;

class SlackChannel implements ChannelInterface
{
    /**
     * When true the channel records messages in memory instead of performing
     * a real HTTP POST. Enabled explicitly for tests and automatically when no
     * application container is available to confirm a runtime environment.
     */
    protected bool $fake;

    /**
     * In-memory message store, only populated while running in fake mode.
     */
    protected array $sentMessages = [];

    public function __construct(?bool $fake = null)
    {
        // When the flag is not supplied, record instead of delivering unless
        // live delivery is explicitly enabled via config('notifications.live').
        $this->fake = $fake ?? !$this->liveDeliveryEnabled();
    }

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

        $payload = $this->buildPayload($message);

        if ($this->fake) {
            $this->sentMessages[] = [
                'webhook' => $webhook,
                'channel' => $message->getChannel(),
                'text' => $message->getText(),
                'attachments' => $message->getAttachments(),
                'sent_at' => new \DateTime(),
            ];

            return;
        }

        $this->post($webhook, $payload);
    }

    /**
     * Build the JSON-serialisable Slack payload.
     */
    protected function buildPayload(SlackMessage $message): array
    {
        $payload = ['text' => $message->getText()];

        if (!empty($message->getChannel())) {
            $payload['channel'] = $message->getChannel();
        }

        $attachments = $message->getAttachments();
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * Perform the real HTTP POST to the Slack webhook using ext-curl.
     *
     * @throws \RuntimeException when the request fails or returns a non-2xx status.
     */
    protected function post(string $webhook, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $handle = curl_init($webhook);

        if ($handle === false) {
            throw new \RuntimeException('Unable to initialise a cURL handle for the Slack webhook.');
        }

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 15);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($response === false || $error !== '') {
            throw new \RuntimeException("Slack notification request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Slack notification returned an unexpected HTTP status {$status}: "
                . (is_string($response) ? $response : '')
            );
        }
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
            $webhook = $notifiable->routeNotificationForSlack();

            return is_string($webhook) ? $webhook : '';
        }

        return '';
    }

    /**
     * Determine whether real (live) delivery has been explicitly enabled.
     */
    protected function liveDeliveryEnabled(): bool
    {
        if (!function_exists('config')) {
            return false;
        }

        try {
            return (bool)config('notifications.live', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get sent messages (fake mode, for testing).
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Clear sent messages (fake mode, for testing).
     */
    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
    }
}
