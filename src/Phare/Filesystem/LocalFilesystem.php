<?php

declare(strict_types=1);

namespace Phare\Filesystem;

class LocalFilesystem extends Filesystem
{
    public function __construct(private readonly string $root) {}

    public function exists(string $path): bool
    {
        return parent::exists($this->prefix($path));
    }

    public function get(string $path): string|false
    {
        return parent::get($this->prefix($path));
    }

    public function put(string $path, string $contents, bool $lock = false): int|false
    {
        return parent::put($this->prefix($path), $contents, $lock);
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        return parent::delete(array_map(fn ($p) => $this->prefix($p), $paths));
    }

    public function copy(string $path, string $target): bool
    {
        return parent::copy($this->prefix($path), $this->prefix($target));
    }

    public function move(string $path, string $target): bool
    {
        return parent::move($this->prefix($path), $this->prefix($target));
    }

    public function size(string $path): int
    {
        return parent::size($this->prefix($path));
    }

    public function lastModified(string $path): int
    {
        return parent::lastModified($this->prefix($path));
    }

    public function isFile(string $file): bool
    {
        return parent::isFile($this->prefix($file));
    }

    public function isDirectory(string $directory): bool
    {
        return parent::isDirectory($this->prefix($directory));
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        return parent::makeDirectory($this->prefix($path), $mode, $recursive, $force);
    }

    public function deleteDirectory(string $directory): bool
    {
        return parent::deleteDirectory($this->prefix($directory));
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    protected function prefix(string $path): string
    {
        if ($path === '') {
            return rtrim($this->root, '/');
        }

        if ($this->isAbsolute($path)) {
            return $path;
        }

        return rtrim($this->root, '/') . '/' . ltrim($path, '/');
    }

    protected function isAbsolute(string $path): bool
    {
        return $path !== '' && ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1);
    }
}
