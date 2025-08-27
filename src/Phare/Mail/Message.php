<?php

namespace Phare\Mail;

class Message
{
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected array $replyTo = [];
    protected string $subject = '';

    public function to(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->to = array_merge($this->to, $address);
        } else {
            $this->to[$address] = $name;
        }
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->cc = array_merge($this->cc, $address);
        } else {
            $this->cc[$address] = $name;
        }
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->bcc = array_merge($this->bcc, $address);
        } else {
            $this->bcc[$address] = $name;
        }
        return $this;
    }

    public function replyTo(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->replyTo = array_merge($this->replyTo, $address);
        } else {
            $this->replyTo[$address] = $name;
        }
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}