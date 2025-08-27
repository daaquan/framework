<?php

namespace Phare\Queue;

use Phare\Queue\Connectors\ConnectorInterface;

class QueueManager
{
    protected array $connectors = [];
    protected array $connections = [];
    protected string $defaultConnection = 'sync';
    protected array $config;

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
        $config = $this->getConfig($name);
        $connector = $this->getConnector($config['driver']);

        return $connector->connect($config);
    }

    /**
     * Get the configuration for a connection.
     */
    protected function getConfig(string $name): array
    {
        return $this->config['connections'][$name] ?? [];
    }

    /**
     * Get a connector instance.
     */
    protected function getConnector(string $driver): ConnectorInterface
    {
        if (!isset($this->connectors[$driver])) {
            throw new \InvalidArgumentException("No connector for [{$driver}]");
        }

        return $this->connectors[$driver]($this->getConfig($driver));
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
            $job->handle();
        } catch (\Exception $e) {
            $this->handleFailedJob($job, $e);
        }
    }

    /**
     * Handle a failed job.
     */
    protected function handleFailedJob(Job $job, \Exception $exception): void
    {
        $job->incrementRetries();

        if ($job->canRetry()) {
            // Re-queue the job with a delay
            $job->delay(60); // 1 minute delay before retry
            $this->push($job);
        } else {
            // Job has exceeded max retries, call failed handler
            $job->failed($exception);
        }
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
}