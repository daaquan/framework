<?php

namespace Phare\Queue;

trait Queueable
{
    /**
     * Dispatch the job to the queue.
     */
    public static function dispatch(...$arguments): string
    {
        $job = new static(...$arguments);

        return app('queue')->push($job);
    }

    /**
     * Dispatch the job to the queue after a delay.
     */
    public static function dispatchAfter(int $delay, ...$arguments): string
    {
        $job = new static(...$arguments);

        return app('queue')->later($job, $delay);
    }

    /**
     * Dispatch the job to a specific queue.
     */
    public static function dispatchOn(string $queue, ...$arguments): string
    {
        $job = new static(...$arguments);
        $job->onQueue($queue);

        return app('queue')->push($job);
    }

    /**
     * Dispatch the job using a specific connection.
     */
    public static function dispatchUsing(string $connection, ...$arguments): string
    {
        $job = new static(...$arguments);

        return app('queue')->push($job, null, $connection);
    }
}
