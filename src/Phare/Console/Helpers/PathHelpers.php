<?php

namespace Phare\Console\Helpers;

use Phare\Collections\Str;

trait PathHelpers
{
    abstract public function validate(array $settings);

    abstract public function get($value);

    abstract public function has($value);

    private function getDir(string $key, string $path = ''): string
    {
        $this->validate(['application', $key]);

        return Str::append($this->get('application')->get($key), '/') . $path;
    }

    public function getAppPath(): string
    {
        return $this->getDir('appDir');
    }

    public function getEntityDirectory(string $path = ''): string
    {
        return $this->getDir('entitiesDir', $path);
    }

    public function getControllerDirectory(string $path = ''): string
    {
        return $this->getDir('controllersDir', $path);
    }

    public function getDatabaseDirectory(): string
    {
        return $this->getDir('databaseDir');
    }

    public function getMigrationDirectory(string $path = ''): string
    {
        return $this->getDatabaseDirectory() . 'migrations/' . $path;
    }

    public function getFactoryDirectory(string $path = ''): string
    {
        return $this->getDatabaseDirectory() . 'factories/' . $path;
    }

    public function getSeedDirectory(string $path = ''): string
    {
        return $this->getDatabaseDirectory() . 'seeds/' . $path;
    }

    public function getEnumDirectory(string $path = ''): string
    {
        return $this->getDir('enumDir', $path);
    }

    public function getConfigDirectory(string $path = ''): string
    {
        return $this->getDir('configDir', $path);
    }

    public function getCacheDirectory(string $path = ''): string
    {
        return $this->getDir('cacheDir', $path);
    }

    public function getVendorDirectory(string $path = ''): string
    {
        return $this->getDir('vendorDir', $path);
    }

    public function getConsoleDirectory(string $path = ''): string
    {
        return $this->getDir('consoleDir', $path);
    }

    public function getCommandsDirectory(string $path = ''): string
    {
        if ($this->has(['application', 'commandsDir'])) {
            return $this->getDir('commandsDir', $path);
        }

        return $this->getConsoleDirectory('commands/' . $path);
    }

    public function getResourcesDirectory(string $path = ''): ?string
    {
        $full = $this->getAppPath() . "../resources/$path";
        $real = realpath($full);

        return $real ?: $full; // Return the raw path when realpath fails
    }

    public function getMigrationDDLDirectory(string $path = ''): ?string
    {
        return $this->getResourcesDirectory("database/migrations/$path");
    }

    public function getAllDatabaseDirectories(): array
    {
        return [
            'database' => $this->getDatabaseDirectory(),
            'migrations' => $this->getMigrationDirectory(),
            'factories' => $this->getFactoryDirectory(),
            'seeds' => $this->getSeedDirectory(),
        ];
    }
}
