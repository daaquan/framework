<?php

namespace Phare\Cache\Adapter;

use Phalcon\Cache\Adapter\AdapterInterface as CacheAdapterInterface;

class NullAdapter implements CacheAdapterInterface
{
    public function __construct(private readonly string $prefix = '') {}

    public function clear(): bool
    {
        return true;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        return true;
    }

    public function get(string $key, $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function getAdapter(): mixed
    {
        return null;
    }

    public function getKeys(string $prefix = ''): array
    {
        return [];
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return false;
    }

    public function set(string $key, $value, $ttl = null): bool
    {
        return false;
    }

    public function setForever(string $key, $value): bool
    {
        return false;
    }
}
