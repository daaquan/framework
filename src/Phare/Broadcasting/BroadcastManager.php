<?php

namespace Phare\Broadcasting;

use InvalidArgumentException;
use Phare\Broadcasting\Broadcasters\Broadcaster;
use Phare\Broadcasting\Broadcasters\LogBroadcaster;
use Phare\Broadcasting\Broadcasters\NullBroadcaster;
use Phare\Broadcasting\Broadcasters\PusherBroadcaster;
use Phare\Broadcasting\Broadcasters\RedisBroadcaster;
use Phare\Container\Container;

class BroadcastManager
{
    protected Container $container;

    protected array $broadcasters = [];

    protected array $customCreators = [];

    protected ?string $defaultDriver = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function driver(?string $name = null): Broadcaster
    {
        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->broadcasters[$name])) {
            return $this->broadcasters[$name];
        }

        return $this->broadcasters[$name] = $this->resolve($name);
    }

    public function connection(?string $name = null): Broadcaster
    {
        return $this->driver($name);
    }

    protected function resolve(string $name): Broadcaster
    {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    protected function callCustomCreator(array $config): Broadcaster
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

    public function extend(string $driver, callable $callback): self
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
        unset($this->broadcasters[$name]);
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

    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
