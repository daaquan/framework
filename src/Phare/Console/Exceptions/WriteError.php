<?php

namespace Phare\Console\Exceptions;

use Exception;

class WriteError extends Exception
{
    /**
     * Writing to the filesystem failed.
     *
     * @param string $path
     * @return static
     */
    public static function fileWriteFailed($e, $path = '')
    {
        $message = "Writing to {$path} failed." . "\n" . $e;

        return new static($message);
    }

    /**
     * Writing to the filesystem failed because the given class exists.
     *
     * @param string $class
     * @return static
     */
    public static function classExists($class)
    {
        return new static("Class {$class} already exists.");
    }

    /**
     * Writing to the filesystem failed because the given entity exists.
     *
     * @param string $class
     * @return static
     */
    public static function entityExists($class)
    {
        return new static("Entity {$class} already exists.");
    }

    /**
     * Writing to the filesystem failed because the given enum exists.
     *
     * @param string $class
     * @return static
     */
    public static function enumExists($class)
    {
        return new static("Enum {$class} already exists.");
    }

    /**
     * Writing to the filesystem failed because the given cache exists.
     *
     * @param string $fileName
     * @return static
     */
    public static function cacheFileExists($fileName)
    {
        return new static("Cache {$fileName} already exists.");
    }

    /**
     * A command with the given name already exists.
     *
     * @param string $name
     * @return static
     */
    public static function commandExists($name)
    {
        return new static(
            "Could not create command {$name}. " .
            "Command with name {$name} already exists."
        );
    }

    /**
     * A seeder with the given name already exists.
     *
     * @param string $name
     * @return static
     */
    public static function seederExists($name)
    {
        return new static(
            "Could not create seeder {$name}. " .
            "Seeder with name {$name} already exists."
        );
    }
}
