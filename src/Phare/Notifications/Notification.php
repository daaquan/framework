<?php

namespace Phare\Notifications;

abstract class Notification
{
    protected string $id;

    protected array $data = [];

    protected ?\DateTimeInterface $readAt = null;

    protected \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->id = $this->generateId();
        $this->createdAt = new \DateTime();
    }

    protected function generateId(): string
    {
        return uniqid('notification_', true);
    }

    /**
     * Get the notification's delivery channels.
     */
    abstract public function via(mixed $notifiable): array;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): ?Messages\MailMessage
    {
        return null;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(mixed $notifiable): array
    {
        return [];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): ?Messages\SmsMessage
    {
        return null;
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(mixed $notifiable): ?Messages\SlackMessage
    {
        return null;
    }

    /**
     * Determine if the notification should be sent.
     */
    public function shouldSend(mixed $notifiable, string $channel): bool
    {
        return true;
    }

    /**
     * Set additional data for the notification.
     */
    public function with(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Get the notification's unique identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the notification ID.
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the notification's additional data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->readAt = new \DateTime();
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->readAt = null;
    }

    /**
     * Determine if the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * Get the time the notification was read.
     */
    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    /**
     * Get the time the notification was created.
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set the time the notification was read.
     */
    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }
}
