<?php

namespace Tests\Support;

use Phare\Contracts\Foundation\Application;

class SimpleApplication implements Application
{
    private array $bindings = [];

    private array $singletons = [];

    private array $resolved = [];

    public function version(): string
    {
        return '1.0.0';
    }

    public function basePath(string $path = ''): string
    {
        return __DIR__ . '/../Mock' . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath('bootstrap' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function environment(): string
    {
        return 'testing';
    }

    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }

    public function runningUnitTests(): bool
    {
        return true;
    }

    public function hasBeenBootstrapped(): bool
    {
        return true;
    }

    public function bootstrap(array $bootstrappers): void
    {
        // No-op for testing
    }

    public function getLoadedProviders(): array
    {
        return [];
    }

    public function providerIsLoaded(string $provider): bool
    {
        return false;
    }

    public function register($provider, bool $force = false): void
    {
        // No-op for testing
    }

    public function registerDeferredProvider(string $provider, ?string $service = null): void
    {
        // No-op for testing
    }

    public function resolveProvider(string $provider): void
    {
        // No-op for testing
    }

    public function boot(): void
    {
        // No-op for testing
    }

    public function booting(\Closure $callback): void
    {
        // No-op for testing
    }

    public function booted(\Closure $callback): void
    {
        // No-op for testing
    }

    public function getCachedServicesPath(): string
    {
        return '';
    }

    public function getCachedPackagesPath(): string
    {
        return '';
    }

    public function configurationIsCached(): bool
    {
        return false;
    }

    public function getCachedConfigPath(): string
    {
        return '';
    }

    public function routesIsCached(): bool
    {
        return false;
    }

    public function getCachedRoutesPath(): string
    {
        return '';
    }

    public function bind($abstract, $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function bindIf($abstract, $concrete = null, bool $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    public function singleton($abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function singletonIf($abstract, $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    public function extend($abstract, \Closure $closure): void
    {
        // No-op for testing
    }

    public function instance($abstract, $instance): void
    {
        $this->resolved[$abstract] = $instance;
    }

    public function tag($abstracts, $tags): void
    {
        // No-op for testing
    }

    public function tagged(string $tag): iterable
    {
        return [];
    }

    public function make($abstract, array $parameters = []): mixed
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \Exception("Service {$abstract} not found");
        }

        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        if (is_callable($concrete)) {
            $instance = $concrete($this, $parameters);
        } elseif (is_string($concrete)) {
            $instance = new $concrete();
        } else {
            $instance = $concrete;
        }

        if ($binding['shared']) {
            $this->resolved[$abstract] = $instance;
        }

        return $instance;
    }

    public function call($callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        return call_user_func($callback);
    }

    public function resolved(string $abstract): bool
    {
        return isset($this->resolved[$abstract]);
    }

    public function resolving($abstract, ?\Closure $callback = null): void
    {
        // No-op for testing
    }

    public function afterResolving($abstract, ?\Closure $callback = null): void
    {
        // No-op for testing
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->resolved[$abstract]);
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->bindings[$alias] = $this->bindings[$abstract] ?? null;
    }

    public function getAlias(string $abstract): string
    {
        return $abstract;
    }

    public function forgetInstance($abstract): void
    {
        unset($this->resolved[$abstract]);
    }

    public function forgetInstances(): void
    {
        $this->resolved = [];
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->resolved = [];
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return $this->bound($id);
    }
}
