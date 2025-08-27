<?php

namespace Phare\Queue;

interface QueueInterface
{
    /**
     * Push a job onto the queue.
     */
    public function push(Job $job, ?string $queue = null): string;

    /**
     * Pop a job from the queue.
     */
    public function pop(?string $queue = null): ?Job;

    /**
     * Get the size of the queue.
     */
    public function size(?string $queue = null): int;

    /**
     * Clear all jobs from the queue.
     */
    public function clear(?string $queue = null): int;

    /**
     * Delete a job from the queue.
     */
    public function delete(Job $job): bool;
}