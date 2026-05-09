<?php

declare(strict_types=1);

namespace Phare\Filesystem;

use InvalidArgumentException;
use Phalcon\Config\Config;

class FilesystemManager
{
    /**
     * @var array<string, Filesystem>
     */
    protected array $disks = [];

    protected string $defaultDisk;

    public function __construct()
    {
        $this->defaultDisk = (string)config('filesystems.default', 'local');
    }

    /**
     * Resolve a configured filesystem disk. Null returns the default disk.
     */
    public function disk(?string $name = null): Filesystem
    {
        $name = $name ?? $this->defaultDisk;

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        $config = $this->normalizeConfig(config("filesystems.disks.{$name}"));

        if ($config === [] || !isset($config['driver'])) {
            throw new InvalidArgumentException("Filesystem disk '{$name}' is not configured.");
        }

        return $this->disks[$name] = $this->resolveDriver($config['driver'], $config);
    }

    public function getDefaultDisk(): string
    {
        return $this->defaultDisk;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolveDriver(string $driver, array $config): Filesystem
    {
        return match ($driver) {
            'local' => $this->createLocalDriver($config),
            'null' => new NullFilesystem(),
            default => throw new InvalidArgumentException("Filesystem driver '{$driver}' is not supported."),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createLocalDriver(array $config): LocalFilesystem
    {
        if (empty($config['root']) || !is_string($config['root'])) {
            throw new InvalidArgumentException("Local filesystem disk requires a 'root' path.");
        }

        return new LocalFilesystem($config['root']);
    }

    protected function normalizeConfig(mixed $value): array
    {
        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
