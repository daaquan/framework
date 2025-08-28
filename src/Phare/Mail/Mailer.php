<?php

namespace Phare\Mail;

class Mailer
{
    protected array $config;

    protected array $sentMails = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'smtp',
            'host' => 'localhost',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from' => [
                'address' => 'noreply@example.com',
                'name' => 'Phare Application',
            ],
        ], $config);
    }

    public function send(Mailable $mailable): bool
    {
        try {
            $mailable->build();

            // Store mail for testing/logging purposes
            $this->sentMails[] = [
                'to' => $mailable->getTo(),
                'cc' => $mailable->getCc(),
                'bcc' => $mailable->getBcc(),
                'replyTo' => $mailable->getReplyTo(),
                'subject' => $mailable->getSubject(),
                'htmlBody' => $mailable->hasHtml() ? $mailable->getHtmlBody() : null,
                'textBody' => $mailable->hasText() ? $mailable->getTextBody() : null,
                'attachments' => $mailable->getAttachments(),
                'headers' => $mailable->getHeaders(),
                'sent_at' => date('Y-m-d H:i:s'),
            ];

            return true;
        } catch (\Exception $e) {
            throw new MailException('Failed to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function raw(string $text, \Closure $callback): bool
    {
        $message = new Message();
        $callback($message);

        $mailable = new RawMailable($text, $message);

        return $this->send($mailable);
    }

    public function html(string $html, \Closure $callback): bool
    {
        $message = new Message();
        $callback($message);

        $mailable = new HtmlMailable($html, $message);

        return $this->send($mailable);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getSentMails(): array
    {
        return $this->sentMails;
    }

    public function clearSentMails(): void
    {
        $this->sentMails = [];
    }
}
