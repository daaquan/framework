<?php

namespace Phare\Notifications\Messages;

use Phare\Mail\Mailable;

class MailMessage
{
    protected string $subject = '';

    protected string $greeting = '';

    protected array $introLines = [];

    protected array $outroLines = [];

    protected ?string $actionText = null;

    protected ?string $actionUrl = null;

    protected string $level = 'info';

    protected array $to = [];

    protected array $cc = [];

    protected array $bcc = [];

    protected array $replyTo = [];

    protected array $attachments = [];

    protected ?string $view = null;

    protected array $viewData = [];

    /**
     * Set the subject of the notification.
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the greeting of the notification.
     */
    public function greeting(string $greeting): static
    {
        $this->greeting = $greeting;

        return $this;
    }

    /**
     * Add a line to the notification intro.
     */
    public function line(string $line): static
    {
        $this->introLines[] = $line;

        return $this;
    }

    /**
     * Add lines to the notification intro.
     */
    public function lines(array $lines): static
    {
        foreach ($lines as $line) {
            $this->line($line);
        }

        return $this;
    }

    /**
     * Set the call to action of the notification.
     */
    public function action(string $text, string $url): static
    {
        $this->actionText = $text;
        $this->actionUrl = $url;

        return $this;
    }

    /**
     * Set the notification level.
     */
    public function level(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Set the notification as a success message.
     */
    public function success(): static
    {
        $this->level = 'success';

        return $this;
    }

    /**
     * Set the notification as an error message.
     */
    public function error(): static
    {
        $this->level = 'error';

        return $this;
    }

    /**
     * Add recipients to the message.
     */
    public function to(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->to = array_merge($this->to, $address);
        } else {
            $this->to[$address] = $name;
        }

        return $this;
    }

    /**
     * Add CC recipients to the message.
     */
    public function cc(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->cc = array_merge($this->cc, $address);
        } else {
            $this->cc[$address] = $name;
        }

        return $this;
    }

    /**
     * Add BCC recipients to the message.
     */
    public function bcc(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->bcc = array_merge($this->bcc, $address);
        } else {
            $this->bcc[$address] = $name;
        }

        return $this;
    }

    /**
     * Set the reply-to address for the message.
     */
    public function replyTo(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            $this->replyTo = array_merge($this->replyTo, $address);
        } else {
            $this->replyTo[$address] = $name;
        }

        return $this;
    }

    /**
     * Attach a file to the message.
     */
    public function attach(string $path, ?string $name = null): static
    {
        $this->attachments[] = compact('path', 'name');

        return $this;
    }

    /**
     * Set the view for the message.
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = $data;

        return $this;
    }

    /**
     * Convert the mail message to a mailable.
     */
    public function toMailable(string $to, mixed $notifiable): Mailable
    {
        return new class($this, $to, $notifiable) extends Mailable
        {
            public function __construct(
                protected MailMessage $message,
                protected string $recipient,
                protected mixed $notifiable
            ) {}

            public function build(): void
            {
                $this->to($this->recipient)
                    ->subject($this->message->getSubject());

                if ($this->message->hasView()) {
                    $this->view($this->message->getView(), $this->message->getViewData());
                } else {
                    $this->html($this->buildContent());
                }

                foreach ($this->message->getCc() as $address => $name) {
                    $this->cc($address, $name);
                }

                foreach ($this->message->getBcc() as $address => $name) {
                    $this->bcc($address, $name);
                }

                foreach ($this->message->getReplyTo() as $address => $name) {
                    $this->replyTo($address, $name);
                }

                foreach ($this->message->getAttachments() as $attachment) {
                    $this->attach($attachment['path'], $attachment['name']);
                }
            }

            protected function buildContent(): string
            {
                $content = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

                if ($greeting = $this->message->getGreeting()) {
                    $content .= '<h1>' . htmlspecialchars($greeting) . '</h1>';
                }

                foreach ($this->message->getIntroLines() as $line) {
                    $content .= '<p>' . htmlspecialchars($line) . '</p>';
                }

                if ($this->message->hasAction()) {
                    $content .= '<div style="text-align: center; margin: 30px 0;">';
                    $content .= '<a href="' . htmlspecialchars($this->message->getActionUrl()) . '" ';
                    $content .= 'style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">';
                    $content .= htmlspecialchars($this->message->getActionText());
                    $content .= '</a></div>';
                }

                foreach ($this->message->getOutroLines() as $line) {
                    $content .= '<p>' . htmlspecialchars($line) . '</p>';
                }

                $content .= '</div>';

                return $content;
            }
        };
    }

    // Getters
    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getGreeting(): string
    {
        return $this->greeting;
    }

    public function getIntroLines(): array
    {
        return $this->introLines;
    }

    public function getOutroLines(): array
    {
        return $this->outroLines;
    }

    public function getActionText(): ?string
    {
        return $this->actionText;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function getLevel(): string
    {
        return $this->level;
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

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function hasTo(): bool
    {
        return !empty($this->to);
    }

    public function hasAction(): bool
    {
        return $this->actionText && $this->actionUrl;
    }

    public function hasView(): bool
    {
        return $this->view !== null;
    }
}
