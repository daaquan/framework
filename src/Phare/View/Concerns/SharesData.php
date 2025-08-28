<?php

namespace Phare\View\Concerns;

trait SharesData
{
    /**
     * The shared view data.
     */
    protected static array $sharedData = [];

    /**
     * Share data across all views.
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            static::$sharedData = array_merge(static::$sharedData, $key);
        } else {
            static::$sharedData[$key] = $value;
        }
    }

    /**
     * Get all shared data.
     */
    public static function getShared(): array
    {
        return static::$sharedData;
    }

    /**
     * Clear shared data.
     */
    public static function clearShared(): void
    {
        static::$sharedData = [];
    }

    /**
     * Check if shared data exists.
     */
    public static function hasShared(string $key): bool
    {
        return array_key_exists($key, static::$sharedData);
    }

    /**
     * Get a shared value.
     */
    public static function getSharedValue(string $key, mixed $default = null): mixed
    {
        return static::$sharedData[$key] ?? $default;
    }
}
