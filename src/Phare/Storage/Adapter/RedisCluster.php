<?php

namespace Phare\Storage\Adapter;

use Phalcon\Cache\Adapter\AdapterInterface;
use Phalcon\Storage\Adapter\Redis as RedisAdapter;
use Phalcon\Storage\Exception as StorageException;

class RedisCluster extends RedisAdapter implements AdapterInterface
{
    /**
     * Returns the already connected adapter or connects to the Redis cluster.
     *
     * @throws StorageException
     */
    public function getAdapter(): \RedisCluster
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        try {
            $options = $this->getOptionsWithDefaults();
            $seeds = $this->buildSeeds($options);

            $this->adapter = new \RedisCluster(null, $seeds, $options['timeout'], $options['readTimeout'], $options['persistent'], $options['auth']);
            $this->adapter->setOption(\Redis::OPT_PREFIX, $this->prefix);
            $this->setSerializer($this->adapter);

            return $this->adapter;
        } catch (\RedisClusterException $e) {
            throw new StorageException('Failed to connect to the Redis cluster: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Setups serializer for the Redis cluster connection.
     *
     * @throws StorageException
     */
    private function setSerializer(\RedisCluster $connection): void
    {
        $serializer = strtolower($this->defaultSerializer);
        $map = $this->getSerializerMap();

        if (!array_key_exists($serializer, $map)) {
            throw new StorageException("Serializer '$serializer' is not supported.");
        }

        $connection->setOption(\Redis::OPT_SERIALIZER, $map[$serializer]);
    }

    /**
     * Provides a map of available serializers.
     */
    private function getSerializerMap(): array
    {
        $map = [
            'none' => \Redis::SERIALIZER_NONE,
            'php' => \Redis::SERIALIZER_PHP,
        ];

        if (defined('\\Redis::SERIALIZER_IGBINARY')) {
            $map['igbinary'] = \Redis::SERIALIZER_IGBINARY;
        }

        if (defined('\\Redis::SERIALIZER_MSGPACK')) {
            $map['msgpack'] = \Redis::SERIALIZER_MSGPACK;
        }

        if (defined('\\Redis::SERIALIZER_JSON')) {
            $map['json'] = \Redis::SERIALIZER_JSON;
        }

        return $map;
    }

    /**
     * Builds seeds array from the options.
     */
    private function buildSeeds(array $options): array
    {
        if (isset($options['seeds'])) {
            return $options['seeds'];
        }

        $host = $options['host'];
        $port = $options['port'];

        return [$host . ':' . $port];
    }

    /**
     * Fetches options and sets defaults.
     */
    private function getOptionsWithDefaults(): array
    {
        $defaults = [
            'host' => '127.0.0.1',
            'port' => '6379',
            'auth' => '',
            'persistent' => false,
            'timeout' => 0,
            'readTimeout' => 0,
        ];

        return array_merge($defaults, $this->options);
    }
}
