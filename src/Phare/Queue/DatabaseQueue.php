<?php

namespace Phare\Queue;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum;

class DatabaseQueue implements QueueInterface
{
    protected array $config;

    protected string $table;

    /**
     * The resolved database connection. When null, the queue operates in the
     * in-memory "array" mode used by unit tests instead of persisting rows.
     */
    protected ?AbstractPdo $connection;

    /**
     * In-memory storage used only when no database connection is available
     * (test/array mode). Real persistence goes through $this->connection.
     */
    protected array $jobs = [];

    public function __construct(array $config = [], ?AbstractPdo $connection = null)
    {
        $this->config = array_merge([
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ], $config);

        $this->table = $this->config['table'];
        $this->connection = $connection;
    }

    /**
     * Determine whether the queue persists to a real database connection.
     */
    public function persists(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job, ?string $queue = null): string
    {
        $queue = $queue ?: $this->config['queue'];
        $job->onQueue($queue);

        $payload = json_encode($job->serialize());
        $availableAt = $job->getAvailableAt()?->getTimestamp() ?? time();
        $createdAt = time();

        if ($this->connection === null) {
            $id = count($this->jobs) + 1;
            $this->jobs[] = [
                'id' => $id,
                'queue' => $queue,
                'payload' => $payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $availableAt,
                'created_at' => $createdAt,
            ];

            return (string)$id;
        }

        $this->connection->execute(
            "INSERT INTO {$this->table} (queue, payload, attempts, reserved_at, available_at, created_at)"
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [$queue, $payload, 0, null, $availableAt, $createdAt]
        );

        return (string)$this->connection->lastInsertId();
    }

    /**
     * Pop the next available, unreserved job from the queue and reserve it.
     */
    public function pop(?string $queue = null): ?Job
    {
        $queue = $queue ?: $this->config['queue'];

        if ($this->connection === null) {
            return $this->popFromMemory($queue);
        }

        $now = time();
        $row = $this->connection->fetchOne(
            "SELECT id, payload FROM {$this->table}"
            . ' WHERE queue = ? AND reserved_at IS NULL AND available_at <= ?'
            . ' ORDER BY id ASC LIMIT 1',
            Enum::FETCH_ASSOC,
            [$queue, $now]
        );

        if (!isset($row['id'])) {
            return null;
        }

        // Reserve the job and bump its attempt counter.
        $this->connection->execute(
            "UPDATE {$this->table} SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?",
            [$now, $row['id']]
        );

        $payload = json_decode($row['payload'], true);
        $jobClass = $payload['class'] ?? null;

        if (!is_string($jobClass) || !class_exists($jobClass)) {
            return null;
        }

        return $jobClass::deserialize($payload);
    }

    /**
     * Pop the next available job from the in-memory store (array mode).
     */
    protected function popFromMemory(string $queue): ?Job
    {
        foreach ($this->jobs as $index => $jobData) {
            if ($jobData['queue'] === $queue &&
                $jobData['reserved_at'] === null &&
                $jobData['available_at'] <= time()) {
                $this->jobs[$index]['reserved_at'] = time();
                $this->jobs[$index]['attempts']++;

                $payload = json_decode($jobData['payload'], true);
                $jobClass = $payload['class'] ?? null;

                if (!is_string($jobClass) || !class_exists($jobClass)) {
                    continue;
                }

                return $jobClass::deserialize($payload);
            }
        }

        return null;
    }

    /**
     * Release a reserved job back onto the queue, optionally after a delay.
     */
    public function release(Job $job, int $delay = 0): bool
    {
        $jobId = $job->getJobId();
        $availableAt = time() + max(0, $delay);

        if ($this->connection === null) {
            foreach ($this->jobs as $index => $jobData) {
                $payload = json_decode($jobData['payload'], true);
                if (($payload['job_id'] ?? null) === $jobId) {
                    $this->jobs[$index]['reserved_at'] = null;
                    $this->jobs[$index]['available_at'] = $availableAt;

                    return true;
                }
            }

            return false;
        }

        $affected = $this->connection->execute(
            "UPDATE {$this->table} SET reserved_at = NULL, available_at = ?"
            . ' WHERE payload LIKE ?',
            [$availableAt, '%' . $jobId . '%']
        );

        return (bool)$affected;
    }

    /**
     * Get the size of the queue (unreserved jobs only).
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        if ($this->connection === null) {
            return count(array_filter($this->jobs, function ($job) use ($queue) {
                return $job['queue'] === $queue && $job['reserved_at'] === null;
            }));
        }

        $row = $this->connection->fetchOne(
            "SELECT COUNT(*) AS count FROM {$this->table} WHERE queue = ? AND reserved_at IS NULL",
            Enum::FETCH_ASSOC,
            [$queue]
        );

        return (int)($row['count'] ?? 0);
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        if ($this->connection === null) {
            $originalCount = count($this->jobs);

            $this->jobs = array_values(array_filter($this->jobs, function ($job) use ($queue) {
                return $job['queue'] !== $queue;
            }));

            return $originalCount - count($this->jobs);
        }

        $count = $this->size($queue) + $this->reservedCount($this->connection, $queue);

        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE queue = ?",
            [$queue]
        );

        return $count;
    }

    /**
     * Count the reserved jobs on a queue (database mode helper).
     */
    protected function reservedCount(AbstractPdo $connection, string $queue): int
    {
        $row = $connection->fetchOne(
            "SELECT COUNT(*) AS count FROM {$this->table} WHERE queue = ? AND reserved_at IS NOT NULL",
            Enum::FETCH_ASSOC,
            [$queue]
        );

        return (int)($row['count'] ?? 0);
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(Job $job): bool
    {
        $jobId = $job->getJobId();

        if ($this->connection === null) {
            foreach ($this->jobs as $index => $jobData) {
                $payload = json_decode($jobData['payload'], true);
                if (($payload['job_id'] ?? null) === $jobId) {
                    unset($this->jobs[$index]);
                    $this->jobs = array_values($this->jobs);

                    return true;
                }
            }

            return false;
        }

        $affected = $this->connection->execute(
            "DELETE FROM {$this->table} WHERE payload LIKE ?",
            ['%' . $jobId . '%']
        );

        return (bool)$affected;
    }

    /**
     * Get all jobs (array/test mode accessor).
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Clear the in-memory store (array/test mode accessor).
     */
    public function flush(): void
    {
        $this->jobs = [];
    }
}
