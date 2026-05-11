<?php

namespace Phare\Cache;

use InvalidArgumentException;
use Phalcon\Cache\Adapter\AdapterInterface as CacheAdapterInterface;
use Phalcon\Cache\Adapter\Apcu;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phalcon\Config\Config;
use Phalcon\Storage\SerializerFactory;
use Phare\Cache\Adapter\ArrayAdapter;
use Phare\Cache\Adapter\NullAdapter;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\Manager;

class CacheManager extends Manager
{
    protected string $defaultStore;

    public function __construct(?ContainerContract $container = null)
    {
        parent::__construct($container ?? $this->resolveContainer());

        $this->defaultStore = (string)config('cache.default', 'file');

        // Eagerly build the default store so misconfiguration surfaces at
        // construction time (matches existing behavior).
        $this->store($this->defaultStore);
    }

    /**
     * Resolve a configured cache store. Null returns the default store.
     */
    public function store(?string $name = null): CacheAdapterInterface
    {
        return $this->driver($name);
    }

    public function adapter(): CacheAdapterInterface
    {
        return $this->store();
    }

    public function getDefaultDriver(): ?string
    {
        return $this->defaultStore;
    }

    public function getDefaultStore(): string
    {
        return $this->defaultStore;
    }

    protected function createDriver(string $driver): mixed
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $config = $this->normalizeConfig(config("cache.stores.{$driver}"));

        if ($config === [] || !isset($config['driver'])) {
            throw new InvalidArgumentException("Cache config for '{$driver}' is invalid or missing.");
        }

        return $this->makeAdapter($config['driver'], $config);
    }

    protected function makeAdapter(string $driver, array $config): CacheAdapterInterface
    {
        $factory = new SerializerFactory();

        return match ($driver) {
            'file', 'stream' => $this->makeStreamAdapter($factory, $config),
            'redis' => $this->makeRedisAdapter($factory, $config),
            'apc', 'apcu' => $this->makeApcuAdapter($factory, $config),
            'array' => $this->makeArrayAdapter($factory, $config),
            'null' => $this->makeNullAdapter($config),
            default => throw new InvalidArgumentException("Invalid cache driver: {$driver}"),
        };
    }

    protected function makeStreamAdapter(SerializerFactory $factory, array $config): Stream
    {
        if (empty($config['path'])) {
            throw new InvalidArgumentException('File cache: storage path is not set.');
        }

        return new Stream($factory, ['storageDir' => $config['path']]);
    }

    protected function makeRedisAdapter(SerializerFactory $factory, array $config): Redis
    {
        $connection = (string)($config['connection'] ?? 'default');

        $conn = $this->normalizeConfig(config("database.connections.redis.{$connection}"));

        if ($conn === []) {
            $conn = $this->normalizeConfig(config("database.connections.cache.{$connection}"));
        }

        if ($conn === []) {
            $conn = $this->normalizeConfig(config("database.connections.{$connection}"));
        }

        if ($conn === []) {
            throw new InvalidArgumentException('Redis cache: connection config is missing.');
        }

        return new Redis($factory, [
            'host' => $conn['host'] ?? '127.0.0.1',
            'port' => $conn['port'] ?? 6379,
            'persistent' => $conn['persistent'] ?? false,
        ]);
    }

    protected function makeApcuAdapter(SerializerFactory $factory, array $config): Apcu
    {
        return new Apcu($factory, $config);
    }

    protected function makeArrayAdapter(SerializerFactory $factory, array $config): ArrayAdapter
    {
        unset($factory);

        return new ArrayAdapter($config['prefix'] ?? config('cache.prefix', ''));
    }

    protected function makeNullAdapter(array $config): NullAdapter
    {
        return new NullAdapter($config['prefix'] ?? config('cache.prefix', ''));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store()->get($key);

        return $value !== null ? $value : $default;
    }

    public function set(string $key, mixed $value, int|string|null $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    public function clear(): bool
    {
        return $this->store()->clear();
    }

    protected function resolveContainer(): ContainerContract
    {
        $app = app();

        if ($app instanceof ContainerContract) {
            return $app;
        }

        return new Container();
    }

    protected function normalizeConfig(mixed $value): array
    {
        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
