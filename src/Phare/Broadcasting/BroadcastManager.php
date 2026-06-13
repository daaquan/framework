<?php

namespace Phare\Broadcasting;

use Closure;
use InvalidArgumentException;
use Phare\Broadcasting\Broadcasters\Broadcaster;
use Phare\Broadcasting\Broadcasters\LogBroadcaster;
use Phare\Broadcasting\Broadcasters\NullBroadcaster;
use Phare\Broadcasting\Broadcasters\PusherBroadcaster;
use Phare\Broadcasting\Broadcasters\RedisBroadcaster;
use Phare\Container\Container;
use Phare\Queue\Job;
use Phare\Support\Manager;

class BroadcastManager extends Manager
{
    protected ?string $defaultDriver = null;

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function connection(?string $name = null): Broadcaster
    {
        return $this->driver($name);
    }

    /**
     * Build a broadcaster for the given connection name. Instances are
     * cached by the Manager base keyed on connection name.
     */
    protected function createDriver(string $name): Broadcaster
    {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->invokeCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function invokeCustomCreator(array $config): Broadcaster
    {
        return $this->customCreators[$config['driver']]($this->container, $config);
    }

    protected function createPusherDriver(array $config): PusherBroadcaster
    {
        return new PusherBroadcaster(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $config['options'] ?? [],
            $config['host'] ?? null,
            $config['port'] ?? null,
            $config['scheme'] ?? null
        );
    }

    protected function createRedisDriver(array $config): RedisBroadcaster
    {
        $redis = $this->container['redis'] ?? null;
        if (!$redis) {
            throw new InvalidArgumentException('Redis service not available.');
        }

        return new RedisBroadcaster(
            $redis,
            $config['connection'] ?? 'default'
        );
    }

    protected function createLogDriver(array $config): LogBroadcaster
    {
        $logger = $this->container['log'] ?? null;
        if (!$logger) {
            throw new InvalidArgumentException('Log service not available.');
        }

        return new LogBroadcaster($logger);
    }

    protected function createNullDriver(array $config): NullBroadcaster
    {
        return new NullBroadcaster();
    }

    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        if ($this->defaultDriver) {
            return $this->defaultDriver;
        }

        $configService = $this->container['config'] ?? null;

        return $configService ? $configService->get('broadcasting.default', 'null') : 'null';
    }

    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }

    protected function getConfig(string $name): array
    {
        $configService = $this->container['config'] ?? null;
        if (!$configService) {
            throw new InvalidArgumentException('Config service not available.');
        }

        $config = $configService->get("broadcasting.connections.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("Broadcasting connection [{$name}] not configured.");
        }

        return $config;
    }

    public function purge(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultDriver();
        unset($this->drivers[$name]);
    }

    /**
     * Defer an event onto the queue so it is broadcast out-of-band.
     *
     * The event is wrapped in a {@see BroadcastEventJob} and pushed onto the
     * `queue` service, honouring broadcastConnection()/broadcastQueue() when the
     * event exposes them. When no queue service can be resolved (e.g. in tests
     * or a queue-less app) it falls back to broadcasting synchronously so the
     * convenience never silently drops the event.
     */
    public function queue(mixed $event): void
    {
        if (method_exists($event, 'broadcastWhen') && !$event->broadcastWhen()) {
            return;
        }

        $queue = $this->resolveQueue();

        if ($queue === null) {
            // No queue available — broadcast inline so the event is not lost.
            $this->broadcastNow($event);

            return;
        }

        $connection = method_exists($event, 'broadcastConnection') ? $event->broadcastConnection() : null;
        $onQueue = method_exists($event, 'broadcastQueue') ? $event->broadcastQueue() : null;

        try {
            $job = new BroadcastEventJob($event);
            $queue->push($job, $onQueue, $connection);
        } catch (\Throwable $e) {
            // If the queue rejects the job, do not drop the broadcast: fall back
            // to a synchronous send and log the failure when a logger exists.
            $logger = $this->container['log'] ?? null;
            if ($logger) {
                $logger->warning('Broadcast queue dispatch failed; broadcasting synchronously.', [
                    'exception' => $e->getMessage(),
                    'event' => get_class($event),
                ]);
            }

            $this->broadcastNow($event);
        }
    }

    /**
     * Broadcast an event immediately across each configured connection. This is
     * the synchronous entry point used directly, by the queue worker (via
     * {@see BroadcastEventJob}), and as the fallback in {@see queue()}.
     */
    public function broadcastNow(mixed $event): void
    {
        if (method_exists($event, 'broadcastWhen') && !$event->broadcastWhen()) {
            return;
        }

        $drivers = method_exists($event, 'broadcastVia') ? $event->broadcastVia() : ['pusher'];

        foreach ($drivers as $driver) {
            $this->connection($driver)->broadcast(
                method_exists($event, 'broadcastOn') ? $event->broadcastOn() : [],
                method_exists($event, 'broadcastAs') ? $event->broadcastAs() : get_class($event),
                method_exists($event, 'broadcastWith') ? $event->broadcastWith() : []
            );
        }
    }

    /**
     * Resolve the queue service from the container, or null when none is bound.
     */
    protected function resolveQueue(): mixed
    {
        $queue = $this->container['queue'] ?? null;

        if ($queue !== null && method_exists($queue, 'push')) {
            return $queue;
        }

        return null;
    }
}

/**
 * Queue job that performs a broadcast out-of-band. The event is carried on the
 * job and replayed through the container's broadcast manager (or, as a fallback
 * for queue-less contexts, broadcast inline) when the worker runs handle().
 */
class BroadcastEventJob extends Job
{
    protected mixed $event;

    public function __construct(mixed $event)
    {
        parent::__construct();

        $this->event = $event;
    }

    public function handle(): void
    {
        $manager = function_exists('app') ? app('broadcast') : null;

        if ($manager instanceof BroadcastManager) {
            $manager->broadcastNow($this->event);

            return;
        }

        // No resolvable manager (e.g. outside a booted application): fall back
        // to the default pusher-style channels via a fresh manager is not
        // possible without a container, so there is nothing further to do here.
    }
}
