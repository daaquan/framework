<?php

namespace Phare\Queue;

use Phare\Queue\Connectors\ConnectorInterface;

class QueueManager
{
    protected array $connectors = [];

    protected array $connections = [];

    protected string $defaultConnection = 'sync';

    protected array $config;

    protected array $beforeCallbacks = [];

    protected array $afterCallbacks = [];

    protected array $loopingCallbacks = [];

    protected array $failingCallbacks = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'sync';
        $this->registerDefaultConnectors();
    }

    /**
     * Register the default queue connectors.
     */
    protected function registerDefaultConnectors(): void
    {
        $this->connectors['sync'] = function ($config) {
            return new Connectors\SyncConnector();
        };

        $this->connectors['database'] = function ($config) {
            return new Connectors\DatabaseConnector($config);
        };

        $this->connectors['redis'] = function ($config) {
            return new Connectors\RedisConnector($config);
        };
    }

    /**
     * Get a queue connection instance.
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Make a new queue connection.
     */
    protected function makeConnection(string $name): QueueInterface
    {
        $config = $this->getConnectionConfig($name);
        $driver = $config['driver'] ?? null;
        if (!is_string($driver) || $driver === '') {
            throw new \InvalidArgumentException("Queue connection [{$name}] is missing a valid driver.");
        }

        $connector = $this->getConnector($driver, $config);

        return $connector->connect($config);
    }

    /**
     * Get the configuration for a connection.
     */
    protected function getConnectionConfig(string $name): array
    {
        $config = $this->config['connections'][$name] ?? null;
        if (!is_array($config)) {
            throw new \InvalidArgumentException("The [{$name}] queue connection has not been configured.");
        }

        return $config;
    }

    /**
     * Get a connector instance.
     */
    protected function getConnector(string $driver, array $config = []): ConnectorInterface
    {
        if (!isset($this->connectors[$driver])) {
            throw new \InvalidArgumentException("No connector for [{$driver}]");
        }

        return $this->connectors[$driver]($config);
    }

    /**
     * Add a new queue connector.
     */
    public function extend(string $driver, \Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver;
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job, ?string $queue = null, ?string $connection = null): string
    {
        return $this->connection($connection)->push($job, $queue);
    }

    /**
     * Push a job onto the queue after a delay.
     */
    public function later(Job $job, int $delay, ?string $queue = null, ?string $connection = null): string
    {
        $job->delay($delay);

        return $this->connection($connection)->push($job, $queue);
    }

    /**
     * Pop a job from the queue.
     */
    public function pop(?string $queue = null, ?string $connection = null): ?Job
    {
        return $this->connection($connection)->pop($queue);
    }

    /**
     * Get the size of a queue.
     */
    public function size(?string $queue = null, ?string $connection = null): int
    {
        return $this->connection($connection)->size($queue);
    }

    /**
     * Clear all jobs from a queue.
     */
    public function clear(?string $queue = null, ?string $connection = null): int
    {
        return $this->connection($connection)->clear($queue);
    }

    /**
     * Process jobs from the queue.
     */
    public function work(?string $queue = null, ?string $connection = null, int $maxJobs = 0): void
    {
        $connection = $this->connection($connection);
        $processed = 0;

        while (true) {
            $this->invokeLoopingCallbacks();

            $job = $connection->pop($queue);

            if ($job === null) {
                // No jobs available, sleep for a bit
                sleep(1);

                continue;
            }

            $this->processJob($job);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /**
     * Process a single job.
     */
    protected function processJob(Job $job): void
    {
        try {
            $this->invokeBeforeCallbacks($job);
            $job->handle();
            $this->invokeAfterCallbacks($job);
        } catch (\Exception $e) {
            $this->handleFailedJob($job, $e);
        }
    }

    /**
     * Handle a failed job.
     */
    protected function handleFailedJob(Job $job, \Exception $exception): void
    {
        $this->invokeFailingCallbacks($job, $exception);
        $job->incrementRetries();

        if ($job->canRetry()) {
            try {
                // Re-queue the job with a delay
                $job->delay(60); // 1 minute delay before retry
                $this->push($job);

                return;
            } catch (\Exception) {
                // Fall through to failed handler when immediate requeue execution fails.
            }
        }

        // Job has exceeded max retries, or re-queue failed.
        $job->failed($exception);
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Get all connections.
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get the queue configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Determine if the given connection has been resolved.
     */
    public function connected(?string $name = null): bool
    {
        $name = $name ?: $this->getDefaultConnection();

        return isset($this->connections[$name]);
    }

    /**
     * Register a callback for jobs before handling.
     */
    public function before(callable $callback): void
    {
        $this->beforeCallbacks[] = $callback;
    }

    /**
     * Register a callback for jobs after successful handling.
     */
    public function after(callable $callback): void
    {
        $this->afterCallbacks[] = $callback;
    }

    /**
     * Register a callback that runs on every worker loop.
     */
    public function looping(callable $callback): void
    {
        $this->loopingCallbacks[] = $callback;
    }

    /**
     * Register a callback for failed jobs.
     */
    public function failing(callable $callback): void
    {
        $this->failingCallbacks[] = $callback;
    }

    protected function invokeBeforeCallbacks(Job $job): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $callback($job);
        }
    }

    protected function invokeAfterCallbacks(Job $job): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $callback($job);
        }
    }

    protected function invokeLoopingCallbacks(): void
    {
        foreach ($this->loopingCallbacks as $callback) {
            $callback();
        }
    }

    protected function invokeFailingCallbacks(Job $job, \Exception $exception): void
    {
        foreach ($this->failingCallbacks as $callback) {
            $callback($job, $exception);
        }
    }
}
