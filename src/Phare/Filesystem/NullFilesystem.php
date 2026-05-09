<?php

declare(strict_types=1);

namespace Phare\Filesystem;

class NullFilesystem extends Filesystem
{
    public function exists(string $path): bool
    {
        return false;
    }

    public function get(string $path): string|false
    {
        return false;
    }

    public function put(string $path, string $contents, bool $lock = false): int|false
    {
        return strlen($contents);
    }

    public function delete(string|array $paths): bool
    {
        return true;
    }

    public function copy(string $path, string $target): bool
    {
        return true;
    }

    public function move(string $path, string $target): bool
    {
        return true;
    }

    public function size(string $path): int
    {
        return 0;
    }

    public function lastModified(string $path): int
    {
        return 0;
    }

    public function isFile(string $file): bool
    {
        return false;
    }

    public function isDirectory(string $directory): bool
    {
        return false;
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        return true;
    }
}
