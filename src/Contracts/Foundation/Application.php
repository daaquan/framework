<?php

declare(strict_types=1);

namespace Phare\Contracts\Foundation;

interface Application
{
    /**
     * Get the version number of the application.
     */
    public function version(): string;

    /**
     * Get the base path of the App installation.
     */
    public function basePath(string $path = ''): string;

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
