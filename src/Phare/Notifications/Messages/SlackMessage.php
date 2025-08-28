<?php

namespace Phare\Notifications\Messages;

class SlackMessage
{
    protected string $text = '';

    protected string $channel = '';

    protected string $webhook = '';

    protected array $attachments = [];

    protected string $username = '';

    protected string $icon = '';

    /**
     * Create a new Slack message.
     */
    public function __construct(string $text = '')
    {
        $this->text = $text;
    }

    /**
     * Set the message text.
     */
    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Set the channel to send the message to.
     */
    public function to(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Set the webhook URL.
     */
    public function webhook(string $webhook): static
    {
        $this->webhook = $webhook;

        return $this;
    }

    /**
     * Set the username for the message.
     */
    public function username(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Set the icon for the message.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Add an attachment to the message.
     */
    public function attachment(array $attachment): static
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * Set the level of the message.
     */
    public function success(): static
    {
        return $this->attachment([
            'color' => 'good',
            'text' => $this->text,
        ]);
    }

    /**
     * Set the message as an error.
     */
    public function error(): static
    {
        return $this->attachment([
            'color' => 'danger',
            'text' => $this->text,
        ]);
    }

    /**
     * Set the message as a warning.
     */
    public function warning(): static
    {
        return $this->attachment([
            'color' => 'warning',
            'text' => $this->text,
        ]);
    }

    /**
     * Get the message text.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Get the channel.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Get the webhook URL.
     */
    public function getWebhook(): string
    {
        return $this->webhook;
    }

    /**
     * Get the username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get the icon.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get the attachments.
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Determine if the message has a webhook.
     */
    public function hasWebhook(): bool
    {
        return !empty($this->webhook);
    }
}
