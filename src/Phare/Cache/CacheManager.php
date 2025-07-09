<?php

namespace Phare\Cache;

use InvalidArgumentException;
use Phalcon\Cache\Adapter\AdapterInterface as CacheAdapterInterface;
use Phalcon\Cache\Adapter\Apcu;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phalcon\Storage\SerializerFactory;

class CacheManager
{
    protected CacheAdapterInterface $cache;

    public function __construct()
    {
        $store = config('cache.default', 'file');
        $config = config("cache.stores.{$store}");

        if (!$config || !isset($config['driver'])) {
            throw new InvalidArgumentException("Cache config for '{$store}' is invalid or missing.");
        }

        $this->cache = $this->makeAdapter($config['driver'], $config);
    }

    public function adapter(): CacheAdapterInterface
    {
        return $this->cache;
    }

    protected function makeAdapter(string $driver, array $config): CacheAdapterInterface
    {
        $factory = new SerializerFactory();

        return match ($driver) {
            'file', 'stream' => $this->makeStreamAdapter($factory, $config),
            'redis' => $this->makeRedisAdapter($factory, $config),
            'apc', 'apcu' => $this->makeApcuAdapter($factory, $config),
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
        $conn = config("database.connections.redis.{$config['connection']}");
        if (!$conn) {
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

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->cache->get($key);

        return $value !== null ? $value : $default;
    }

    public function set(string $key, mixed $value, int|string|null $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->flush();
    }
}
