<?php

namespace Phare\Contracts\Foundation\Bus;

use Phare\Queue\Job;
use Phare\Queue\QueueManager;

class PendingDispatch
{
    protected bool $dispatched = false;

    protected ?string $queue = null;

    protected ?string $connection = null;

    public function __construct(protected mixed $job) {}

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        if (method_exists($this->job, 'onQueue')) {
            $this->job->onQueue($queue);
        }

        return $this;
    }

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function delay(int $seconds): static
    {
        if (method_exists($this->job, 'delay')) {
            $this->job->delay($seconds);
        }

        return $this;
    }

    public function resolve(): static
    {
        if ($this->dispatched) {
            return $this;
        }

        $queue = app('queue');
        if ($queue instanceof QueueManager && $this->job instanceof Job) {
            $queue->push($this->job, $this->queue, $this->connection);
            $this->dispatched = true;

            return $this;
        }

        if (is_object($this->job) && method_exists($this->job, 'handle')) {
            $this->job->handle();
            $this->dispatched = true;
        }

        return $this;
    }

    public function getJob(): mixed
    {
        return $this->job;
    }

    public function __call(string $name, array $arguments): static
    {
        if (is_object($this->job) && method_exists($this->job, $name)) {
            $this->job->{$name}(...$arguments);
        }

        return $this;
    }

    public function __destruct()
    {
        $this->resolve();
    }
}
