<?php

namespace Phare\Cache\Adapter;

use DateInterval;
use DateTimeImmutable;
use Phalcon\Cache\Adapter\AdapterInterface as CacheAdapterInterface;

class ArrayAdapter implements CacheAdapterInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    private array $storage = [];

    public function __construct(private readonly string $prefix = '') {}

    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function delete(string $key): bool
    {
        $itemKey = $this->itemKey($key);
        if (!array_key_exists($itemKey, $this->storage)) {
            return false;
        }

        unset($this->storage[$itemKey]);

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        $allDeleted = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $allDeleted = false;
            }
        }

        return $allDeleted;
    }

    public function getMultiple(array $keys, $defaultValue = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $defaultValue);
        }

        return $values;
    }

    public function setMultiple(array $values, $ttl = null): bool
    {
        $allSet = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string)$key, $value, $ttl)) {
                $allSet = false;
            }
        }

        return $allSet;
    }

    public function get(string $key, $defaultValue = null): mixed
    {
        $itemKey = $this->itemKey($key);
        if (!isset($this->storage[$itemKey])) {
            return $defaultValue;
        }

        $entry = $this->storage[$itemKey];
        $expiresAt = $entry['expires_at'];
        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->storage[$itemKey]);

            return $defaultValue;
        }

        return $entry['value'];
    }

    public function getAdapter(): mixed
    {
        return $this;
    }

    public function getKeys(string $prefix = ''): array
    {
        $target = $this->prefix . $prefix;
        $keys = [];

        foreach (array_keys($this->storage) as $key) {
            if ($target === '' || str_starts_with($key, $target)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $itemKey = $this->itemKey($key);
        $current = $this->get($key, 0);
        $next = (int)$current + $value;

        $expiresAt = $this->storage[$itemKey]['expires_at'] ?? null;
        $this->storage[$itemKey] = ['value' => $next, 'expires_at' => $expiresAt];

        return $next;
    }

    public function set(string $key, $value, $ttl = null): bool
    {
        $seconds = $this->secondsUntil($ttl);
        if ($seconds !== null && $seconds <= 0) {
            return $this->delete($key);
        }

        $this->storage[$this->itemKey($key)] = [
            'value' => $value,
            'expires_at' => $seconds === null ? null : time() + $seconds,
        ];

        return true;
    }

    public function setForever(string $key, $value): bool
    {
        $this->storage[$this->itemKey($key)] = [
            'value' => $value,
            'expires_at' => null,
        ];

        return true;
    }

    private function itemKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function secondsUntil($ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            $ttl = (new DateTimeImmutable())->add($ttl);
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->getTimestamp() - time();
        }

        return (int)$ttl;
    }
}
