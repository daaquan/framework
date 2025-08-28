<?php

namespace Phare\Queue;

class DatabaseQueue implements QueueInterface
{
    protected array $config;

    protected array $jobs = []; // In-memory storage for testing

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ], $config);
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job, ?string $queue = null): string
    {
        $queue = $queue ?: $this->config['queue'];
        $job->onQueue($queue);

        // In a real implementation, this would insert into database
        // For now, store in memory for testing
        $this->jobs[] = [
            'id' => count($this->jobs) + 1,
            'queue' => $queue,
            'payload' => json_encode($job->serialize()),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $job->getAvailableAt()?->getTimestamp() ?? time(),
            'created_at' => time(),
        ];

        return (string)count($this->jobs);
    }

    /**
     * Pop a job from the queue.
     */
    public function pop(?string $queue = null): ?Job
    {
        $queue = $queue ?: $this->config['queue'];

        foreach ($this->jobs as $index => $jobData) {
            if ($jobData['queue'] === $queue &&
                $jobData['reserved_at'] === null &&
                $jobData['available_at'] <= time()) {
                // Mark as reserved
                $this->jobs[$index]['reserved_at'] = time();
                $this->jobs[$index]['attempts']++;

                // Deserialize the job
                $payload = json_decode($jobData['payload'], true);
                $jobClass = $payload['class'];

                if (!class_exists($jobClass)) {
                    continue;
                }

                return $jobClass::deserialize($payload);
            }
        }

        return null;
    }

    /**
     * Get the size of the queue.
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        return count(array_filter($this->jobs, function ($job) use ($queue) {
            return $job['queue'] === $queue && $job['reserved_at'] === null;
        }));
    }

    /**
     * Clear all jobs from the queue.
     */
    public function clear(?string $queue = null): int
    {
        $queue = $queue ?: $this->config['queue'];

        $originalCount = count($this->jobs);

        $this->jobs = array_filter($this->jobs, function ($job) use ($queue) {
            return $job['queue'] !== $queue;
        });

        return $originalCount - count($this->jobs);
    }

    /**
     * Delete a job from the queue.
     */
    public function delete(Job $job): bool
    {
        $jobId = $job->getJobId();

        foreach ($this->jobs as $index => $jobData) {
            $payload = json_decode($jobData['payload'], true);
            if ($payload['job_id'] === $jobId) {
                unset($this->jobs[$index]);
                $this->jobs = array_values($this->jobs); // Re-index array

                return true;
            }
        }

        return false;
    }

    /**
     * Get all jobs (for testing purposes).
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Clear all jobs (for testing purposes).
     */
    public function flush(): void
    {
        $this->jobs = [];
    }
}
