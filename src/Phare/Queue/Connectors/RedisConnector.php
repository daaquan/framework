<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\QueueInterface;
use Phare\Queue\RedisQueue;

class RedisConnector implements ConnectorInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Establish a queue connection backed by a real ext-redis client.
     *
     * A real client is built whenever a connection target (host) is
     * configured. When ext-redis is unavailable a clear RuntimeException is
     * thrown at connect time rather than silently falling back to a mock.
     * When no host is configured the queue runs in array/test mode.
     */
    public function connect(array $config): QueueInterface
    {
        $config = array_merge($this->config, $config);

        return new RedisQueue($config, $this->resolveClient($config));
    }

    /**
     * Build and connect an ext-redis client from config, or return null to
     * signal array/test mode when no connection target is configured.
     */
    protected function resolveClient(array $config): ?\Redis
    {
        if (isset($config['_client']) && $config['_client'] instanceof \Redis) {
            return $config['_client'];
        }

        // No connection target configured -> array/test mode.
        if (empty($config['host']) && empty($config['unix_socket'])) {
            return null;
        }

        if (!extension_loaded('redis') || !class_exists(\Redis::class)) {
            throw new \RuntimeException(
                'The Redis queue connection requires the "redis" PHP extension (ext-redis), which is not installed.'
            );
        }

        $client = new \Redis();

        $host = $config['unix_socket'] ?? ($config['host'] ?? '127.0.0.1');
        $port = isset($config['unix_socket']) ? 0 : (int)($config['port'] ?? 6379);
        $timeout = (float)($config['timeout'] ?? 0.0);

        if (!$client->connect($host, $port, $timeout)) {
            throw new \RuntimeException("Unable to connect to Redis at {$host}:{$port}.");
        }

        if (!empty($config['password'])) {
            $client->auth((string)$config['password']);
        }

        if (isset($config['database'])) {
            $client->select((int)$config['database']);
        }

        return $client;
    }
}
