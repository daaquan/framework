<?php

declare(strict_types=1);

namespace Phare\Contracts\Foundation;

interface Application extends Container
{
    /**
     * Get the version number of the application.
     */
    public function version(): string;

    /**
     * Determine if a service is registered on the container.
     */
    public function has(string $name): bool;

    /**
     * Determine if the application routes are cached.
     */
    public function routesIsCached(): bool;

    /**
     * Get the base path of the App installation.
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the path to the bootstrap directory.
     */
    public function bootstrapPath(string $path = ''): string;

    /**
     * Get the path to the application configuration files.
     */
    public function configPath(string $path = ''): string;

    /**
     * Get the path to the database directory.
     */
    public function databasePath(string $path = ''): string;

    /**
     * Get the path to the language files.
     */
    public function languagePath(string $path = ''): string;

    /**
     * Get the path to the resources directory.
     */
    public function resourcePath(string $path = ''): string;

    /**
     * Get the path to the storage directory.
     */
    public function storagePath(string $path = ''): string;

    /**
     * Get or check the current application environment.
     *
     * @param string|array $environments
     * @return string|bool
     */
    public function environment(...$environments);

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole();

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests();

    /**
     * Boot the application.
     *
     * @return mixed
     */
    public function bootstrapWith(array $bootstrappers);
}
