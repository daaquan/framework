<?php

namespace Phare\Support;

class Env
{
    /**
     * Indicates if the putenv function is enabled.
     *
     * @var bool
     */
    protected static $putenv = true;

    /**
     * Enable the putenv function.
     */
    public static function enablePutenv(): void
    {
        static::$putenv = true;
    }

    /**
     * Disable the putenv function.
     */
    public static function disablePutenv(): void
    {
        static::$putenv = false;
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     */
    public static function get($key, $default = null): mixed
    {
        $value = getenv($key);

        // If environment variable doesn't exist
        if ($value === false) {
            return is_callable($default) ? $default() : $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }
}
