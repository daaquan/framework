<?php

namespace Phare\Queue;

class RedisQueue implements QueueInterface
{
    protected array $config;

    protected array $queues = []; // Mock Redis storage

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ], $config);
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job, ?string $queue = null): string
    {
        $queue = $queue ?: $this->config['queue'];
        $job->onQueue($queue);

        if (!isset($this->queues[$queue])) {
            $this->queues[$queue] = [];
        }

        $jobData = [
            'id' => uniqid(),
            'payload' => json_encode($job->serialize()),
            'pushed_at' => time(),
        ];

        if ($job->getDelay() > 0) {
            // For delayed jobs, add to delayed queue
            $delayedQueue = "delayed:{$queue}";
            if (!isset($this->queues[$delayedQueue])) {
                $this->queues[$delayedQueue] = [];
            }
            $jobData['available_at'] = time() + $job->getDelay();
            $this->queues[$delayedQueue][] = $jobData;
        } else {
            $this->queues[$queue][] = $jobData;
        }

        return $jobData['id'];
    }

    /**
     * Pop a job from the queue.
     */
    public function pop(?string $queue = null): ?Job
    {
        $queue = $queue ?: $this->config['queue'];

        // First, move any ready delayed jobs to the main queue
        $this->moveDelayedJobs($queue);

        if (!isset($this->queues[$queue]) || empty($this->queues[$queue])) {
            return null;
        }

        $jobData = array_shift($this->queues[$queue]);
        $payload = json_decode($jobData['payload'], true);
        $jobClass = $payload['class'];

        if (!class_exists($jobClass)) {
            return null;
        }

        return $jobClass::deserialize($payload);
    }

    /**
     * Move delayed jobs that are ready to the main queue.
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

        // Re-index the delayed queue
        $this->queues[$delayedQueue] = array_values($this->queues[$delayedQueue]);

        // Add ready jobs to main queue
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

        return isset($this->queues[$queue]) ? count($this->queues[$queue]) : 0;
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        $count = $this->size($queue);
        $this->queues[$queue] = [];

        // Also clear delayed queue
        $delayedQueue = "delayed:{$queue}";
        if (isset($this->queues[$delayedQueue])) {
            $count += count($this->queues[$delayedQueue]);
            $this->queues[$delayedQueue] = [];
        }

        return $count;
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(Job $job): bool
    {
        $jobId = $job->getJobId();
        $queue = $job->getQueue();

        if (isset($this->queues[$queue])) {
            foreach ($this->queues[$queue] as $index => $jobData) {
                $payload = json_decode($jobData['payload'], true);
                if ($payload['job_id'] === $jobId) {
                    unset($this->queues[$queue][$index]);
                    $this->queues[$queue] = array_values($this->queues[$queue]);

                    return true;
                }
            }
        }

        // Check delayed queue too
        $delayedQueue = "delayed:{$queue}";
        if (isset($this->queues[$delayedQueue])) {
            foreach ($this->queues[$delayedQueue] as $index => $jobData) {
                $payload = json_decode($jobData['payload'], true);
                if ($payload['job_id'] === $jobId) {
                    unset($this->queues[$delayedQueue][$index]);
                    $this->queues[$delayedQueue] = array_values($this->queues[$delayedQueue]);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all queues (for testing purposes).
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * Clear all queues (for testing purposes).
     */
    public function flush(): void
    {
        $this->queues = [];
    }
}
