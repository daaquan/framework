<?php

namespace Phare\Filesystem\Contracts;

interface Filesystem
{
    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string|false;

    /**
     * Write the contents of a file.
     */
    public function put(string $path, string $contents, bool $lock = false): int|false;

    /**
     * Prepend to a file.
     */
    public function prepend(string $path, string $data): int|false;

    /**
     * Append to a file.
     */
    public function append(string $path, string $data): int|false;

    /**
     * Delete a file.
     */
    public function delete(string|array $paths): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $path, string $target): bool;

    /**
     * Move a file to a new location.
     */
    public function move(string $path, string $target): bool;

    /**
     * Get the file size of a given file.
     */
    public function size(string $path): int;

    /**
     * Get the file's last modification time.
     */
    public function lastModified(string $path): int;

    /**
     * Get an array of all files in a directory.
     */
    public function files(string $directory, bool $hidden = false): array;

    /**
     * Get all of the files from the given directory (recursive).
     */
    public function allFiles(string $directory, bool $hidden = false): array;

    /**
     * Get all of the directories within a given directory.
     */
    public function directories(string $directory): array;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool;

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Empty the specified directory of all files and folders.
     */
    public function cleanDirectory(string $directory): bool;
}
