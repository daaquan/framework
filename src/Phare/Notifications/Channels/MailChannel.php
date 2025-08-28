<?php

namespace Phare\Notifications\Channels;

use Phare\Mail\Mailer;
use Phare\Notifications\Messages\MailMessage;
use Phare\Notifications\Notification;

class MailChannel implements ChannelInterface
{
    protected ?Mailer $mailer;

    public function __construct(?Mailer $mailer = null)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $message = $notification->toMail($notifiable);

        if (!$message instanceof MailMessage) {
            return;
        }

        if (!$this->mailer) {
            // For testing purposes, we'll skip actual sending if no mailer is configured
            return;
        }

        $to = $this->getRecipients($notifiable, $message);

        $this->buildMessage($message, $to, $notifiable, $notification);
    }

    /**
     * Get the recipients for the message.
     */
    protected function getRecipients(mixed $notifiable, MailMessage $message): string
    {
        if ($message->hasTo()) {
            return $message->getTo()[0] ?? '';
        }

        if (method_exists($notifiable, 'routeNotificationForMail')) {
            return $notifiable->routeNotificationForMail();
        }

        if (method_exists($notifiable, 'getEmailForNotifications')) {
            return $notifiable->getEmailForNotifications();
        }

        if (isset($notifiable->email)) {
            return $notifiable->email;
        }

        return '';
    }

    /**
     * Build the mail message.
     */
    protected function buildMessage(MailMessage $message, string $to, mixed $notifiable, Notification $notification): void
    {
        if (empty($to)) {
            return;
        }

        // Create a mailable from the mail message
        $mailable = $message->toMailable($to, $notifiable);

        if ($this->mailer) {
            $this->mailer->send($mailable);
        }
    }
}
