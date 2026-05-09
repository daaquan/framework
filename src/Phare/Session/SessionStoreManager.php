<?php

namespace Phare\Session;

use Phalcon\Session\Adapter\Redis;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phare\Container\Container;
use Phare\Session\Adapter\RedisCluster;
use Phare\Storage\Adapter\RedisCluster as RedisClusterAdapter;

class SessionStoreManager
{
    protected array $stores = [];

    public function __construct(protected Container $app) {}

    public function store(?string $name = null): SessionManager
    {
        $name = $name ?: $this->getDefaultStore();

        if ($name === null) {
            throw new \InvalidArgumentException('No default session store is configured.');
        }

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        return $this->stores[$name] = $this->resolve($name);
    }

    protected function resolve(string $name): SessionManager
    {
        $config = $this->resolveConfigKey("session.stores.{$name}");

        if ($config === null) {
            throw new \InvalidArgumentException("Session store [{$name}] is not defined.");
        }

        $driver = is_array($config) ? ($config['driver'] ?? null) : (is_object($config) && method_exists($config, 'path') ? $config->path('driver') : null);
        if (!is_string($driver) || $driver === '') {
            throw new \InvalidArgumentException("Session store [{$name}] is missing a driver.");
        }

        $adapter = $this->createAdapter($driver, $config);
        $session = (new SessionManager())->setAdapter($adapter);
        $session->start();

        return $session;
    }

    protected function createAdapter(string $driver, mixed $config): mixed
    {
        switch ($driver) {
            case 'file':
                $savePath = is_array($config) ? ($config['files'] ?? null) : $config->path('files');

                return new Stream(['savePath' => $savePath]);
            case 'redis':
                $redis = is_array($config) ? ($config['redis'] ?? []) : $config->path('redis');
                $cluster = is_array($redis) ? ($redis['cluster'] ?? false) : ($redis->cluster ?? false);
                $params = is_array($redis) ? ($redis['default'] ?? []) : $redis->path('default')->toArray();

                if ($cluster) {
                    return new RedisCluster(
                        new RedisClusterAdapter(new SerializerFactory(), $params)
                    );
                }

                return new Redis(new AdapterFactory(new SerializerFactory()), $params);
            default:
                throw new \InvalidArgumentException("Session driver [{$driver}] is not supported.");
        }
    }

    public function getDefaultStore(): ?string
    {
        $value = $this->resolveConfigKey('session.default');

        return is_string($value) ? $value : null;
    }

    protected function resolveConfigKey(string $key): mixed
    {
        if (!$this->app->has('config')) {
            return null;
        }

        $config = $this->app->make('config');

        if (is_array($config)) {
            $segments = explode('.', $key);
            $value = $config;
            foreach ($segments as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }

            return $value;
        }

        if (is_object($config) && method_exists($config, 'path')) {
            return $config->path($key);
        }

        return null;
    }
}
