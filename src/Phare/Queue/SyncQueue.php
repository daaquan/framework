<?php

namespace Phare\Queue;

class SyncQueue implements QueueInterface
{
    /**
     * Push a job onto the queue and execute it immediately.
     */
    public function push(Job $job, ?string $queue = null): string
    {
        // In sync queue, execute immediately
        try {
            $job->handle();
        } catch (\Exception $e) {
            $job->failed($e);
            throw $e;
        }

        return $job->getJobId();
    }

    /**
     * Pop a job from the queue (not applicable for sync queue).
     */
    public function pop(?string $queue = null): ?Job
    {
        return null; // Sync queue doesn't store jobs
    }

    /**
     * Get the size of the queue (always 0 for sync queue).
     */
    public function size(?string $queue = null): int
    {
        return 0; // Sync queue doesn't store jobs
    }

    /**
     * Clear all jobs from the queue (no-op for sync queue).
     */
    public function clear(?string $queue = null): int
    {
        return 0; // Nothing to clear in sync queue
    }

    /**
     * Delete a job from the queue (no-op for sync queue).
     */
    public function delete(Job $job): bool
    {
        return true; // Always successful for sync queue
    }
}