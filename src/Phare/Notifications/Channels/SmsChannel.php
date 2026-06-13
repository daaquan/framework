<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Messages\SmsMessage;
use Phare\Notifications\Notification;

class SmsChannel implements ChannelInterface
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
        $message = $notification->toSms($notifiable);

        if (!$message instanceof SmsMessage) {
            return;
        }

        $to = $this->getRecipients($notifiable, $message);

        if (empty($to)) {
            return;
        }

        if ($this->fake) {
            $this->sentMessages[] = [
                'to' => $to,
                'message' => $message->getContent(),
                'from' => $message->getFrom(),
                'sent_at' => new \DateTime(),
            ];

            return;
        }

        $this->dispatch($to, $message);
    }

    /**
     * Dispatch the SMS through the configured gateway via a real HTTP POST.
     *
     * @throws \RuntimeException when no gateway is configured or the request fails.
     */
    protected function dispatch(string $to, SmsMessage $message): void
    {
        $gateway = $this->getGatewayConfig();

        $endpoint = $gateway['url'] ?? $gateway['endpoint'] ?? null;
        if (!is_string($endpoint) || $endpoint === '') {
            throw new \RuntimeException(
                'No SMS gateway endpoint is configured. Set `services.sms.url` (or `services.sms.endpoint`) '
                . 'to enable SMS notifications.'
            );
        }

        $payload = [
            'to' => $to,
            'from' => $message->getFrom(),
            'message' => $message->getContent(),
        ];

        $this->post($endpoint, $payload, $gateway);
    }

    /**
     * Perform the real HTTP POST to the SMS gateway using ext-curl.
     */
    protected function post(string $endpoint, array $payload, array $gateway): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $handle = curl_init($endpoint);

        if ($handle === false) {
            throw new \RuntimeException('Unable to initialise a cURL handle for the SMS gateway.');
        }

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->buildHeaders($gateway));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 15);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($response === false || $error !== '') {
            throw new \RuntimeException("SMS gateway request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "SMS gateway returned an unexpected HTTP status {$status}: "
                . (is_string($response) ? $response : '')
            );
        }
    }

    /**
     * Build the request headers, applying gateway authentication if present.
     */
    protected function buildHeaders(array $gateway): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($gateway['token'])) {
            $headers[] = 'Authorization: Bearer ' . $gateway['token'];
        } elseif (!empty($gateway['key'])) {
            $headers[] = 'Authorization: Bearer ' . $gateway['key'];
        }

        return $headers;
    }

    /**
     * Resolve the SMS gateway configuration from the container.
     */
    protected function getGatewayConfig(): array
    {
        if (!function_exists('config')) {
            return [];
        }

        try {
            $config = config('services.sms', []);
        } catch (\Throwable) {
            return [];
        }

        if (is_object($config) && method_exists($config, 'toArray')) {
            $config = $config->toArray();
        }

        return is_array($config) ? $config : [];
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
            $recipient = $notifiable->routeNotificationForSms();

            return is_string($recipient) ? $recipient : '';
        }

        if (method_exists($notifiable, 'getPhoneForNotifications')) {
            $recipient = $notifiable->getPhoneForNotifications();

            return is_string($recipient) ? $recipient : '';
        }

        if (isset($notifiable->phone)) {
            return $notifiable->phone;
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
