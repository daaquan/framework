<?php

namespace Phare\Cache;

use Phalcon\Cache\Adapter\AdapterInterface;

/**
 * Null cache adapter that never stores values.
 */
class NullAdapter implements AdapterInterface
{
    public function get(string $key, mixed $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function set(string $key, mixed $value, mixed $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getKeys(string $prefix = ''): array
    {
        return [];
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return false;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return false;
    }

    public function getAdapter(): mixed
    {
        return $this;
    }

    public function getPrefix(): string
    {
        return '';
    }

    public function setForever(string $key, mixed $value): bool
    {
        return true;
    }
}
