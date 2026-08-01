<?php

namespace Phare\Foundation;

use Closure;
use DateInterval;
use DateTimeInterface;
use Phalcon\Cache\Adapter\AdapterInterface;
use Phalcon\Cache\Cache as PhCache;
use Phare\Contracts\Cache\Cache as CacheContract;

/**
 * @method bool clear()
 * @method bool delete(string $key)
 * @method bool deleteMultiple($keys)
 * @method mixed get(string $key, $defaultValue = null)
 * @method AdapterInterface getAdapter()
 * @method array getOptions()
 * @method bool has(string $key)
 * @method bool set(string $key, $value, $ttl = null)
 * @method bool setMultiple($values, $ttl = null)
 * @method bool setOptions(array $options)
 * @method string getExceptionClass()
 */
class Cache extends PhCache implements CacheContract
{
    public function has($key): bool
    {
        return parent::has((string)$key);
    }

    public function get($key, $defaultValue = null): mixed
    {
        if (is_array($key)) {
            return $this->getMultiple($key, $defaultValue);
        }

        $value = parent::get((string)$key, null);

        return $value !== null ? $value : value($defaultValue);
    }

    public function getMultiple($keys, $defaultValue = null): iterable
    {
        $results = [];

        foreach ($keys as $key => $itemDefault) {
            $resolvedKey = is_int($key) ? (string)$itemDefault : (string)$key;
            $resolvedDefault = is_int($key) ? $defaultValue : $itemDefault;
            $results[$resolvedKey] = $this->get($resolvedKey, $resolvedDefault);
        }

        return $results;
    }

    public function pull($key, $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function put($key, $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        if ($ttl === null) {
            return $this->forever($key, $value);
        }

        $seconds = $this->secondsUntil($ttl);
        if ($seconds <= 0) {
            return $this->forget($key);
        }

        return parent::set((string)$key, $value, $seconds);
    }

    public function set($key, $value, $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }

    public function putMany(array $values, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        $result = true;

        foreach ($values as $key => $value) {
            if (!$this->put((string)$key, $value, $ttl)) {
                $result = false;
            }
        }

        return $result;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return $this->putMany(is_array($values) ? $values : iterator_to_array($values), $ttl);
    }

    public function add($key, $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $ttl);
    }

    public function increment($key, $value = 1): int|bool
    {
        return $this->getAdapter()->increment((string)$key, (int)$value);
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->getAdapter()->decrement((string)$key, (int)$value);
    }

    public function forever($key, $value): bool
    {
        return $this->getAdapter()->setForever((string)$key, $value);
    }

    public function forget($key): bool
    {
        return parent::delete((string)$key);
    }

    public function delete(string $key): bool
    {
        return $this->forget($key);
    }

    public function deleteMultiple($keys): bool
    {
        $result = true;

        foreach ($keys as $key) {
            if (!$this->forget((string)$key)) {
                $result = false;
            }
        }

        return $result;
    }

    public function remember(
        $key,
        Closure|DateTimeInterface|DateInterval|int|null $ttl,
        ?Closure $callback = null
    ): mixed {
        if ($ttl instanceof Closure && $callback === null) {
            return $this->rememberForever($key, $ttl);
        }

        if ($callback === null) {
            throw new \InvalidArgumentException('Cache remember callback is required.');
        }

        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function sear($key, Closure $callback): mixed
    {
        return $this->rememberForever($key, $callback);
    }

    public function rememberForever($key, Closure $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    public function flush(): bool
    {
        return $this->clear();
    }

    public function getPrefix(): string
    {
        return $this->getAdapter()->getPrefix();
    }

    protected function secondsUntil(DateTimeInterface|DateInterval|int $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $ttl = (new \DateTimeImmutable())->add($ttl);
        }

        if ($ttl instanceof DateTimeInterface) {
            return max(0, $ttl->getTimestamp() - time());
        }

        return max(0, (int)$ttl);
    }
}
