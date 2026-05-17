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

    public function queue(mixed $event): void
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
}
