<?php

namespace Phare\Notifications\Channels;

use Phalcon\Db\Adapter\Pdo\AbstractPdo as PDO;
use Phare\Notifications\Notification;

class DatabaseChannel implements ChannelInterface
{
    protected string $table = 'notifications';

    /**
     * Optional connection override. When null the channel resolves the
     * default `db` connection from the container at send time.
     */
    protected ?PDO $connection;

    /**
     * When true the channel always records payloads in memory instead of
     * writing to the database, regardless of whether a connection is
     * available. Useful to force a fake in tests that have a booted app.
     */
    protected bool $fake;

    /**
     * In-memory payload store. Populated when running in fake mode or when no
     * database connection can be resolved (an explicit, observable fallback —
     * the payload is retained and retrievable rather than silently dropped).
     */
    protected array $storedNotifications = [];

    public function __construct(?PDO $connection = null, bool $fake = false)
    {
        $this->connection = $connection;
        $this->fake = $fake;
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $data = $this->buildPayload($notifiable, $notification);

        $connection = $this->fake ? null : $this->resolveConnection();

        if ($connection === null) {
            // Either fake mode is forced, or no connection (and no container)
            // could be resolved. Retain the payload in memory so it is never
            // silently dropped and remains inspectable.
            $this->storedNotifications[] = $data;

            return;
        }

        $this->insert($connection, $data);
    }

    /**
     * Persist the notification payload via a parameterized INSERT.
     */
    protected function insert(PDO $connection, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            $placeholders
        );

        $connection->execute($sql, array_values($data));
    }

    /**
     * Build the notification payload row.
     */
    protected function buildPayload(mixed $notifiable, Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $this->getNotifiableId($notifiable),
            'data' => $this->encodeData($notification->toDatabase($notifiable)),
            'read_at' => $notification->getReadAt()?->format('Y-m-d H:i:s'),
            'created_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Encode the data payload to JSON for storage.
     */
    protected function encodeData(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Resolve the database connection to write through. Returns null when no
     * connection (and no container) is available.
     */
    protected function resolveConnection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if (!function_exists('app')) {
            return null;
        }

        try {
            $connection = app('db');
        } catch (\Throwable) {
            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }

    /**
     * Get the notifiable entity's ID.
     */
    protected function getNotifiableId(mixed $notifiable): mixed
    {
        if (method_exists($notifiable, 'getKey')) {
            return $notifiable->getKey();
        }

        if (method_exists($notifiable, 'getId')) {
            return $notifiable->getId();
        }

        if (isset($notifiable->id)) {
            return $notifiable->id;
        }

        return null;
    }

    /**
     * Get stored notifications (fake mode, for testing).
     */
    public function getStoredNotifications(): array
    {
        return array_map(function (array $row) {
            // Decode the JSON `data` column back to an array so existing test
            // assertions that compare against the raw array keep working.
            if (isset($row['data']) && is_string($row['data'])) {
                $decoded = json_decode($row['data'], true);
                if (is_array($decoded)) {
                    $row['data'] = $decoded;
                }
            }

            return $row;
        }, $this->storedNotifications);
    }

    /**
     * Clear stored notifications (fake mode, for testing).
     */
    public function clearStoredNotifications(): void
    {
        $this->storedNotifications = [];
    }
}
