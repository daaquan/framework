<?php

namespace Phare\Notifications\Messages;

class SmsMessage
{
    protected string $content = '';

    protected string $to = '';

    protected string $from = '';

    /**
     * Create a new SMS message.
     */
    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * Set the message content.
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the message recipient.
     */
    public function to(string $to): static
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Set the message sender.
     */
    public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Get the message content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the message recipient.
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Get the message sender.
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Determine if the message has a recipient.
     */
    public function hasTo(): bool
    {
        return !empty($this->to);
    }
}
