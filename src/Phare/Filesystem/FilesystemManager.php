<?php

declare(strict_types=1);

namespace Phare\Filesystem;

use InvalidArgumentException;
use Phalcon\Config\Config;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\Manager;

class FilesystemManager extends Manager
{
    protected string $defaultDisk;

    public function __construct(?ContainerContract $container = null)
    {
        parent::__construct($container ?? $this->resolveContainer());

        $this->defaultDisk = (string)config('filesystems.default', 'local');
    }

    /**
     * Resolve a configured filesystem disk. Null returns the default disk.
     */
    public function disk(?string $name = null): Filesystem
    {
        return $this->driver($name);
    }

    public function getDefaultDriver(): ?string
    {
        return $this->defaultDisk;
    }

    public function getDefaultDisk(): string
    {
        return $this->defaultDisk;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createDriver(string $driver): Filesystem
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $config = $this->normalizeConfig(config("filesystems.disks.{$driver}"));

        if ($config === [] || !isset($config['driver'])) {
            throw new InvalidArgumentException("Filesystem disk '{$driver}' is not configured.");
        }

        return $this->resolveDriver($config['driver'], $config);
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

    protected function resolveContainer(): ContainerContract
    {
        $app = app();

        if ($app instanceof ContainerContract) {
            return $app;
        }

        return new Container();
    }

    protected function normalizeConfig(mixed $value): array
    {
        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
