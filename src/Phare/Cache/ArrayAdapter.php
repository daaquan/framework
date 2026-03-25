<?php

namespace Phare\Cache;

use Phalcon\Cache\Adapter\AdapterInterface;

/**
 * In-memory array cache adapter for testing and ephemeral caching.
 * Implements Phalcon's AdapterInterface without external dependencies.
 */
class ArrayAdapter implements AdapterInterface
{
    /** @var array<string, array{value: mixed, expiry: int|null}> */
    protected array $store = [];

    protected string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function get(string $key, mixed $defaultValue = null): mixed
    {
        $prefixedKey = $this->prefix . $key;

        if (!isset($this->store[$prefixedKey])) {
            return $defaultValue;
        }

        $entry = $this->store[$prefixedKey];

        if ($entry['expiry'] !== null && $entry['expiry'] <= time()) {
            unset($this->store[$prefixedKey]);

            return $defaultValue;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, mixed $ttl = null): bool
    {
        $prefixedKey = $this->prefix . $key;
        $expiry = null;

        if ($ttl !== null) {
            $expiry = time() + (int)$ttl;
        }

        $this->store[$prefixedKey] = [
            'value' => $value,
            'expiry' => $expiry,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;

        if (!isset($this->store[$prefixedKey])) {
            return false;
        }

        unset($this->store[$prefixedKey]);

        return true;
    }

    public function has(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;

        if (!isset($this->store[$prefixedKey])) {
            return false;
        }

        $entry = $this->store[$prefixedKey];

        if ($entry['expiry'] !== null && $entry['expiry'] <= time()) {
            unset($this->store[$prefixedKey]);

            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    /**
     * @deprecated Use clear() instead
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    public function getKeys(string $prefix = ''): array
    {
        $keys = [];
        $searchPrefix = $prefix ?: $this->prefix;

        foreach ($this->store as $key => $entry) {
            if ($searchPrefix === '' || str_starts_with($key, $searchPrefix)) {
                if ($entry['expiry'] === null || $entry['expiry'] > time()) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $prefixedKey = $this->prefix . $key;

        if (!isset($this->store[$prefixedKey])) {
            return false;
        }

        $current = $this->store[$prefixedKey]['value'];
        $this->store[$prefixedKey]['value'] = $current + $value;

        return $this->store[$prefixedKey]['value'];
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values, mixed $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param array<string> $keys
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function getAdapter(): mixed
    {
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setDefaultSerializer(string $serializer): void
    {
        // No-op for array adapter
    }

    public function setForever(string $key, mixed $value): bool
    {
        return $this->set($key, $value);
    }
}
