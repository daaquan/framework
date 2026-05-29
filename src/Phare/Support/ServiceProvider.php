<?php

namespace Phare\Support;

use Phare\Config\Repository;
use Phare\Contracts\Foundation\Container;

abstract class ServiceProvider
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    abstract public function register(): void;

    public function boot(): void
    {
        // Default empty implementation
    }

    /**
     * Get the services provided by the provider (used by deferred providers).
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Register a callback to run before the application boots.
     */
    public function booting(\Closure $callback): void
    {
        if (method_exists($this->app, 'booting')) {
            $this->app->booting($callback);
        }
    }

    /**
     * Register a callback to run after the application boots.
     */
    public function booted(\Closure $callback): void
    {
        if (method_exists($this->app, 'booted')) {
            $this->app->booted($callback);
        }
    }

    /**
     * Merge the given configuration file under the given key.
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        $config = $this->app->make('config');

        if (!$config instanceof Repository) {
            return;
        }

        $fromFile = require $path;
        $existing = $config->get($key, []);

        $config->set($key, array_merge(
            is_array($fromFile) ? $fromFile : [],
            is_array($existing) ? $existing : []
        ));
    }
}
