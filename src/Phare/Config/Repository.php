<?php

namespace Phare\Config;

use ArrayAccess;
use InvalidArgumentException;
use Phare\Collections\Collection;
use Phare\Contracts\Config\Repository as RepositoryContract;
use Phare\Support\Traits\Macroable;

class Repository implements ArrayAccess, RepositoryContract
{
    use Macroable;

    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using "dot" notation.
     */
    public function get(array|string|null $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            return $this->getMany($key);
        }

        if ($key === null) {
            return $this->all();
        }

        return $this->getPathValue($this->items, $key, $default);
    }

    /**
     * Alias of get() for compatibility with Phalcon\Config usage.
     */
    public function path(array|string|null $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Set a configuration value using "dot" notation.
     */
    public function set(array|string $key, mixed $value = null): void
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $configKey => $configValue) {
            $this->setPathValue($this->items, (string)$configKey, $configValue);
        }
    }

    /**
     * Prepend a value to an array configuration value.
     */
    public function prepend(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        array_unshift($array, $value);
        $this->set($key, $array);
    }

    /**
     * Push a value onto an array configuration value.
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }

    public function string(string $key, mixed $default = null): string
    {
        $value = $this->get($key, $default);

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [%s] must be a string, %s given.',
                $key,
                gettype($value)
            ));
        }

        return $value;
    }

    public function integer(string $key, mixed $default = null): int
    {
        $value = $this->get($key, $default);

        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [%s] must be an integer, %s given.',
                $key,
                gettype($value)
            ));
        }

        return $value;
    }

    public function float(string $key, mixed $default = null): float
    {
        $value = $this->get($key, $default);

        if (!is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [%s] must be a float, %s given.',
                $key,
                gettype($value)
            ));
        }

        return $value;
    }

    public function boolean(string $key, mixed $default = null): bool
    {
        $value = $this->get($key, $default);

        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [%s] must be a boolean, %s given.',
                $key,
                gettype($value)
            ));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key, mixed $default = null): array
    {
        $value = $this->get($key, $default);

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [%s] must be an array, %s given.',
                $key,
                gettype($value)
            ));
        }

        return $value;
    }

    public function collection(string $key, mixed $default = null): Collection
    {
        return new Collection($this->array($key, $default));
    }

    /**
     * Get all configuration items.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Determine if the given configuration option exists.
     */
    public function has(string $key): bool
    {
        return $this->hasPath($this->items, $key);
    }

    /**
     * Get many configuration values.
     */
    public function getMany(array $keys): array
    {
        $config = [];

        foreach ($keys as $key => $default) {
            if (is_numeric($key)) {
                [$key, $default] = [$default, null];
            }

            $config[$key] = $this->get($key, $default);
        }

        return $config;
    }

    /**
     * Set multiple configuration values.
     */
    public function setMany(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function merge(array $items): void
    {
        $this->items = array_replace_recursive($this->items, $items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            return;
        }

        $this->set((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset)) {
            return;
        }

        $segments = explode('.', $offset);
        $last = array_pop($segments);

        $target = &$this->items;
        foreach ($segments as $segment) {
            if (!is_array($target) || !array_key_exists($segment, $target) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }

    protected function getPathValue(array $source, string $path, mixed $default = null): mixed
    {
        if ($path === '') {
            return $source;
        }

        $segments = explode('.', $path);
        $current = $source;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    protected function hasPath(array $source, string $path): bool
    {
        if ($path === '') {
            return true;
        }

        $segments = explode('.', $path);
        $current = $source;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    protected function setPathValue(array &$target, string $path, mixed $value): void
    {
        if ($path === '') {
            return;
        }

        $segments = explode('.', $path);
        $current = &$target;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[array_shift($segments)] = $value;
    }
}
