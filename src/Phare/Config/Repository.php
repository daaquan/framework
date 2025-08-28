<?php

namespace Phare\Config;

use Phare\Collections\Arr;

class Repository
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using "dot" notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * Set a configuration value using "dot" notation.
     */
    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
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
        $this->set($key, $value);
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
        return Arr::has($this->items, $key);
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
}
