<?php

namespace Phare\Mail;

abstract class Mailable
{
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected array $replyTo = [];
    protected string $subject = '';
    protected array $attachments = [];
    protected array $headers = [];
    protected array $data = [];
    protected ?string $view = null;
    protected ?string $textView = null;
    protected ?string $htmlContent = null;
    protected ?string $textContent = null;

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

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $type = null): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'type' => $type
        ];
        return $this;
    }

    public function attachData(string $data, string $name, ?string $type = null): static
    {
        $this->attachments[] = [
            'data' => $data,
            'name' => $name,
            'type' => $type
        ];
        return $this;
    }

    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function view(string $view, ?string $textView = null): static
    {
        $this->view = $view;
        $this->textView = $textView;
        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlContent = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->textContent = $text;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // Getters
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

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function hasHtml(): bool
    {
        return !empty($this->htmlContent) || !empty($this->view);
    }

    public function hasText(): bool
    {
        return !empty($this->textContent) || !empty($this->textView);
    }

    public function getHtmlBody(): string
    {
        if (!empty($this->htmlContent)) {
            return $this->renderView($this->htmlContent);
        }

        if (!empty($this->view)) {
            return $this->renderView($this->view);
        }

        return '';
    }

    public function getTextBody(): string
    {
        if (!empty($this->textContent)) {
            return $this->renderView($this->textContent);
        }

        if (!empty($this->textView)) {
            return $this->renderView($this->textView);
        }

        if (!empty($this->htmlContent)) {
            return strip_tags($this->renderView($this->htmlContent));
        }

        if (!empty($this->view)) {
            return strip_tags($this->renderView($this->view));
        }

        return '';
    }

    protected function renderView(string $view): string
    {
        // Simple template rendering - in a full implementation this would
        // integrate with the view system
        $content = $view;
        
        foreach ($this->data as $key => $value) {
            $content = str_replace('{{ $' . $key . ' }}', (string) $value, $content);
        }

        return $content;
    }

    abstract public function build(): void;
}