<?php

namespace Phare\Queue;

use Phalcon\Config\Config;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Queue\Connectors\ConnectorInterface;
use Phare\Support\Manager;

class QueueManager extends Manager
{
    protected array $connectors = [];

    protected string $defaultConnection = 'sync';

    protected array $beforeCallbacks = [];

    protected array $afterCallbacks = [];

    protected array $loopingCallbacks = [];

    protected array $failingCallbacks = [];

    /**
     * Flag toggled by signal handlers to stop the worker loop gracefully
     * after the current job finishes.
     */
    protected bool $shouldQuit = false;

    /**
     * @param array<string, mixed>|ContainerContract $config Queue config block,
     *                                                       or the application container.
     */
    public function __construct(array|ContainerContract $config = [])
    {
        if ($config instanceof ContainerContract) {
            parent::__construct($config);
            $this->config = $config->bound('config') ? $this->normalizeConfig(config('queue')) : [];
        } else {
            parent::__construct($this->resolveContainer());
            $this->config = $config;
        }

        $this->defaultConnection = $this->config['default'] ?? 'sync';
        $this->registerDefaultConnectors();
    }

    /**
     * Get the default driver name (Laravel parity — the default connection).
     */
    public function getDefaultDriver(): ?string
    {
        return $this->defaultConnection;
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
     * Get a queue connection instance (Laravel-parity alias for driver()).
     */
    public function connection(?string $name = null): QueueInterface
    {
        return $this->driver($name);
    }

    /**
     * Build a queue connection. Connection instances are cached by the
     * Manager base keyed on connection name.
     */
    protected function createDriver(string $name): QueueInterface
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
     * Add a new queue connector keyed by driver name.
     */
    public function extend(string $driver, \Closure $resolver): static
    {
        $this->connectors[$driver] = $resolver;

        return $this;
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
     *
     * The loop terminates when any guard is hit: the configured max number of
     * jobs ($maxJobs), the wall-clock budget (the `max_seconds` config key),
     * or a received stop signal (SIGTERM/SIGINT when pcntl is available). It
     * always finishes the current job before stopping.
     *
     * @param int $maxJobs Stop after processing this many jobs (0 = no limit).
     */
    public function work(?string $queue = null, ?string $connection = null, int $maxJobs = 0): void
    {
        $connectionName = $connection;
        $driver = $this->connection($connectionName);

        $maxSeconds = (int)($this->config['max_seconds'] ?? 0);

        $this->shouldQuit = false;
        $this->registerSignalHandlers();

        $processed = 0;
        $startedAt = time();

        while (true) {
            $this->dispatchSignals();

            if ($this->shouldQuit) {
                break;
            }

            if ($maxSeconds > 0 && (time() - $startedAt) >= $maxSeconds) {
                break;
            }

            $this->invokeLoopingCallbacks();

            $job = $driver->pop($queue);

            if ($job === null) {
                // No jobs available; back off briefly, but stay responsive to
                // shutdown signals between iterations.
                sleep(1);

                continue;
            }

            $this->processJob($job, $connectionName);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /**
     * Register SIGTERM/SIGINT handlers when pcntl is available so the worker
     * can stop cleanly after the current job.
     */
    protected function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $handler = function (): void {
            $this->shouldQuit = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    /**
     * Dispatch any pending signals (no-op when pcntl is unavailable).
     */
    protected function dispatchSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    /**
     * Stop the worker loop after the current job.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * Process a single job.
     */
    protected function processJob(Job $job, ?string $connection = null): void
    {
        try {
            $this->invokeBeforeCallbacks($job);
            $job->handle();
            $this->invokeAfterCallbacks($job);
        } catch (\Exception $e) {
            $this->handleFailedJob($job, $e, $connection);
        }
    }

    /**
     * Handle a failed job, retrying on the originating connection/queue so a
     * job that failed on `redis` is retried on `redis` (not the default).
     */
    protected function handleFailedJob(Job $job, \Exception $exception, ?string $connection = null): void
    {
        $this->invokeFailingCallbacks($job, $exception);
        $job->incrementRetries();

        if ($job->canRetry()) {
            try {
                // Re-queue the job with a delay on its originating connection.
                $job->delay(60); // 1 minute delay before retry
                $this->push($job, $job->getQueue(), $connection);

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
     * Get all resolved connections.
     */
    public function getConnections(): array
    {
        return $this->getDrivers();
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

        return array_key_exists($name, $this->getDrivers());
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

    protected function resolveContainer(): ContainerContract
    {
        $app = app();

        if ($app instanceof ContainerContract) {
            return $app;
        }

        return new Container();
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeConfig(mixed $value): array
    {
        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
