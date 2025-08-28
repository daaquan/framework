<?php

namespace Phare\Filesystem;

use Phare\Filesystem\Contracts\Filesystem as FilesystemContract;

class Filesystem implements FilesystemContract
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function get(string $path): string|false
    {
        if ($this->isFile($path)) {
            return file_get_contents($path);
        }

        return false;
    }

    public function put(string $path, string $contents, bool $lock = false): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    public function replace(string $path, string $content): void
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        file_put_contents($tempPath, $content);

        rename($tempPath, $path);
    }

    public function prepend(string $path, string $data): int|false
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    public function append(string $path, string $data): int|false
    {
        return file_put_contents($path, $data, FILE_APPEND | LOCK_EX);
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;

        foreach ($paths as $path) {
            try {
                if (!unlink($path)) {
                    $success = false;
                }
            } catch (\Exception $e) {
                $success = false;
            }
        }

        return $success;
    }

    public function move(string $path, string $target): bool
    {
        $this->ensureDirectoryExists(dirname($target));

        return rename($path, $target);
    }

    public function copy(string $path, string $target): bool
    {
        $this->ensureDirectoryExists(dirname($target));

        return copy($path, $target);
    }

    public function name(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    public function dirname(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function type(string $path): string|false
    {
        return filetype($path);
    }

    public function mimeType(string $path): string|false
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    public function size(string $path): int
    {
        return filesize($path) ?: 0;
    }

    public function lastModified(string $path): int
    {
        return filemtime($path) ?: 0;
    }

    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    public function glob(string $pattern, int $flags = 0): array
    {
        return glob($pattern, $flags) ?: [];
    }

    public function files(string $directory, bool $hidden = false): array
    {
        return iterator_to_array(
            $this->getIterator($directory, $hidden, false),
            false
        );
    }

    public function allFiles(string $directory, bool $hidden = false): array
    {
        return iterator_to_array(
            $this->getIterator($directory, $hidden, true),
            false
        );
    }

    public function directories(string $directory): array
    {
        $directories = [];

        foreach (new \DirectoryIterator($directory) as $dir) {
            if ($dir->isDir() && !$dir->isDot()) {
                $directories[] = $dir->getPathname();
            }
        }

        return $directories;
    }

    public function allDirectories(string $directory): array
    {
        $directories = [];

        foreach ($this->getRecursiveDirectoryIterator($directory) as $dir) {
            if ($dir->isDir() && !$dir->isDot()) {
                $directories[] = $dir->getPathname();
            }
        }

        return $directories;
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    public function moveDirectory(string $from, string $to, bool $overwrite = false): bool
    {
        if ($overwrite && $this->isDirectory($to) && !$this->deleteDirectory($to)) {
            return false;
        }

        return @rename($from, $to) === true;
    }

    public function copyDirectory(string $directory, string $destination, int $options = null): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $options = $options ?: \FilesystemIterator::SKIP_DOTS;

        $this->ensureDirectoryExists($destination, 0755);

        $items = new \FilesystemIterator($directory, $options);

        foreach ($items as $item) {
            $target = $destination . '/' . $item->getBasename();

            if ($item->isDir()) {
                if (!$this->copyDirectory($item->getPathname(), $target, $options)) {
                    return false;
                }
            } else {
                if (!$this->copy($item->getPathname(), $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $items = new \FilesystemIterator($directory);

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                $this->delete($item->getPathname());
            }
        }

        return rmdir($directory);
    }

    public function cleanDirectory(string $directory): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $items = new \FilesystemIterator($directory);

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                $this->delete($item->getPathname());
            }
        }

        return true;
    }

    protected function getIterator(string $directory, bool $hidden, bool $recursive): \Iterator
    {
        if ($recursive) {
            return $this->getRecursiveDirectoryIterator($directory, $hidden ? 0 : \FilesystemIterator::SKIP_DOTS);
        }

        return new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
    }

    protected function getRecursiveDirectoryIterator(string $directory, int $flags = 0): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, $flags ?: \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    protected function ensureDirectoryExists(string $path, int $mode = 0755): void
    {
        if (!$this->isDirectory($path)) {
            $this->makeDirectory($path, $mode, true, true);
        }
    }
}