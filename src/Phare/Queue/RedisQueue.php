<?php

namespace Phare\Queue;

class RedisQueue implements QueueInterface
{
    protected array $config;

    /**
     * The connected ext-redis client. When null, the queue operates in the
     * in-memory "array" mode used by unit tests instead of talking to Redis.
     */
    protected ?\Redis $client;

    /**
     * In-memory storage used only when no Redis client is available
     * (test/array mode). Real I/O goes through $this->client.
     */
    protected array $queues = [];

    public function __construct(array $config = [], ?\Redis $client = null)
    {
        $this->config = array_merge([
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ], $config);

        $this->client = $client;
    }

    /**
     * Determine whether the queue talks to a real Redis server.
     */
    public function connected(): bool
    {
        return $this->client !== null;
    }

    /**
     * Push a job onto the queue. Delayed jobs go to a sorted set keyed by the
     * timestamp at which they become available.
     */
    public function push(Job $job, ?string $queue = null): string
    {
        $queue = $queue ?: $this->config['queue'];
        $job->onQueue($queue);

        $jobData = [
            'id' => uniqid('', true),
            'payload' => json_encode($job->serialize()),
            'pushed_at' => time(),
        ];

        if ($job->getDelay() > 0) {
            $jobData['available_at'] = time() + $job->getDelay();

            if ($this->client === null) {
                $delayedQueue = "delayed:{$queue}";
                $this->queues[$delayedQueue][] = $jobData;
            } else {
                $this->client->zAdd($this->delayedKey($queue), $jobData['available_at'], json_encode($jobData));
            }

            return $jobData['id'];
        }

        if ($this->client === null) {
            $this->queues[$queue][] = $jobData;
        } else {
            $this->client->rPush($this->queueKey($queue), json_encode($jobData));
        }

        return $jobData['id'];
    }

    /**
     * Pop a job from the queue.
     */
    public function pop(?string $queue = null): ?Job
    {
        $queue = $queue ?: $this->config['queue'];

        $this->migrateDelayedJobs($queue);

        $raw = $this->popRaw($queue);

        if ($raw === null) {
            return null;
        }

        $jobData = json_decode($raw, true);
        if (!is_array($jobData) || !isset($jobData['payload'])) {
            return null;
        }

        $payload = json_decode($jobData['payload'], true);
        $jobClass = $payload['class'] ?? null;

        if (!is_string($jobClass) || !class_exists($jobClass)) {
            return null;
        }

        return $jobClass::deserialize($payload);
    }

    /**
     * Pop the next encoded job from the underlying store.
     */
    protected function popRaw(string $queue): ?string
    {
        if ($this->client === null) {
            if (empty($this->queues[$queue])) {
                return null;
            }

            $jobData = array_shift($this->queues[$queue]);
            $encoded = json_encode($jobData);

            return $encoded === false ? null : $encoded;
        }

        $key = $this->queueKey($queue);
        $blockFor = $this->config['block_for'];

        if ($blockFor !== null && $blockFor > 0) {
            // BLPOP returns [key, value] or false/empty on timeout.
            $result = $this->client->blPop([$key], (int)$blockFor);
            $value = is_array($result) ? ($result[1] ?? null) : null;
        } else {
            $value = $this->client->lPop($key);
        }

        return (is_string($value) && $value !== '') ? $value : null;
    }

    /**
     * Move any delayed jobs that are now ready onto the main queue.
     */
    protected function migrateDelayedJobs(string $queue): void
    {
        if ($this->client === null) {
            $this->moveDelayedJobs($queue);

            return;
        }

        $now = time();
        $delayedKey = $this->delayedKey($queue);
        $ready = $this->client->zRangeByScore($delayedKey, '-inf', (string)$now);

        if (empty($ready)) {
            return;
        }

        foreach ($ready as $member) {
            // Only the winner of the atomic removal gets to migrate the member.
            if ($this->client->zRem($delayedKey, $member) > 0) {
                $this->client->rPush($this->queueKey($queue), $member);
            }
        }
    }

    /**
     * Move ready delayed jobs to the main queue (array/test mode).
     */
    protected function moveDelayedJobs(string $queue): void
    {
        $delayedQueue = "delayed:{$queue}";

        if (!isset($this->queues[$delayedQueue])) {
            return;
        }

        $currentTime = time();
        $readyJobs = [];

        foreach ($this->queues[$delayedQueue] as $index => $jobData) {
            if ($jobData['available_at'] <= $currentTime) {
                $readyJobs[] = $jobData;
                unset($this->queues[$delayedQueue][$index]);
            }
        }

        $this->queues[$delayedQueue] = array_values($this->queues[$delayedQueue]);

        if (!empty($readyJobs)) {
            if (!isset($this->queues[$queue])) {
                $this->queues[$queue] = [];
            }
            $this->queues[$queue] = array_merge($this->queues[$queue], $readyJobs);
        }
    }

    /**
     * Get the size of the queue.
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        if ($this->client === null) {
            return isset($this->queues[$queue]) ? count($this->queues[$queue]) : 0;
        }

        return (int)$this->client->lLen($this->queueKey($queue));
    }

    /**
     * Clear all jobs from the queue (including the delayed set).
     */
    public function clear(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        if ($this->client === null) {
            $count = $this->size($queue);
            $this->queues[$queue] = [];

            $delayedQueue = "delayed:{$queue}";
            if (isset($this->queues[$delayedQueue])) {
                $count += count($this->queues[$delayedQueue]);
                $this->queues[$delayedQueue] = [];
            }

            return $count;
        }

        $key = $this->queueKey($queue);
        $delayedKey = $this->delayedKey($queue);

        $count = (int)$this->client->lLen($key) + (int)$this->client->zCard($delayedKey);

        $this->client->del($key);
        $this->client->del($delayedKey);

        return $count;
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(Job $job): bool
    {
        $jobId = $job->getJobId();
        $queue = $job->getQueue();

        if ($this->client === null) {
            return $this->deleteFromMemory($jobId, $queue);
        }

        return $this->deleteFromRedis($jobId, $queue);
    }

    /**
     * Delete a job from the in-memory store (array/test mode).
     */
    protected function deleteFromMemory(string $jobId, string $queue): bool
    {
        foreach ([$queue, "delayed:{$queue}"] as $key) {
            if (!isset($this->queues[$key])) {
                continue;
            }

            foreach ($this->queues[$key] as $index => $jobData) {
                $payload = json_decode($jobData['payload'], true);
                if (($payload['job_id'] ?? null) === $jobId) {
                    unset($this->queues[$key][$index]);
                    $this->queues[$key] = array_values($this->queues[$key]);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Delete a job from Redis by scanning the list and the delayed set.
     */
    protected function deleteFromRedis(string $jobId, string $queue): bool
    {
        $client = $this->client;
        if ($client === null) {
            return false;
        }

        $key = $this->queueKey($queue);

        foreach ($client->lRange($key, 0, -1) as $member) {
            if ($this->memberHasJobId($member, $jobId)) {
                // lRem removes matching elements; count 1 removes the first.
                return $client->lRem($key, $member, 1) > 0;
            }
        }

        $delayedKey = $this->delayedKey($queue);
        foreach ($client->zRange($delayedKey, 0, -1) as $member) {
            if ($this->memberHasJobId($member, $jobId)) {
                return $client->zRem($delayedKey, $member) > 0;
            }
        }

        return false;
    }

    /**
     * Determine whether an encoded queue member wraps the given job id.
     */
    protected function memberHasJobId(string $member, string $jobId): bool
    {
        $jobData = json_decode($member, true);
        if (!is_array($jobData) || !isset($jobData['payload'])) {
            return false;
        }

        $payload = json_decode($jobData['payload'], true);

        return is_array($payload) && ($payload['job_id'] ?? null) === $jobId;
    }

    /**
     * Build the Redis key for a queue's main list.
     */
    protected function queueKey(string $queue): string
    {
        $prefix = $this->config['prefix'] ?? 'queues';

        return "{$prefix}:{$queue}";
    }

    /**
     * Build the Redis key for a queue's delayed sorted set.
     */
    protected function delayedKey(string $queue): string
    {
        return $this->queueKey($queue) . ':delayed';
    }

    /**
     * Get all queues (array/test mode accessor).
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * Clear the in-memory store (array/test mode accessor).
     */
    public function flush(): void
    {
        $this->queues = [];
    }
}
