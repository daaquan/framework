<?php

namespace Phare\Contracts\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;

interface Cache
{
    public function has(array|string $key): bool;

    public function get(array|string $key, mixed $default = null): mixed;

    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    public function pull(array|string $key, mixed $default = null): mixed;

    public function put(array|string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool;

    public function set(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool;

    public function putMany(array $values, DateTimeInterface|DateInterval|int|null $ttl = null): bool;

    public function setMultiple(iterable $values, DateTimeInterface|DateInterval|int|null $ttl = null): bool;

    public function add(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool;

    public function increment(string $key, mixed $value = 1): int|bool;

    public function decrement(string $key, mixed $value = 1): int|bool;

    public function forever(string $key, mixed $value): bool;

    public function remember(
        string $key,
        Closure|DateTimeInterface|DateInterval|int|null $ttl,
        ?Closure $callback = null
    ): mixed;

    public function sear(string $key, Closure $callback): mixed;

    public function rememberForever(string $key, Closure $callback): mixed;

    public function forget(string $key): bool;

    public function delete(string $key): bool;

    public function deleteMultiple(iterable $keys): bool;

    public function clear(): bool;

    public function flush(): bool;

    public function getPrefix(): string;
}
