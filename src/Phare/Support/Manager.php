<?php

namespace Phare\Support;

use Closure;
use InvalidArgumentException;
use Phare\Contracts\Foundation\Container as ContainerContract;

/**
 * Laravel-parity abstract manager for multi-driver services.
 *
 * Subclasses implement {@see Manager::getDefaultDriver()} and supply
 * one or more `createXxxDriver()` methods (driver name studly-cased).
 *
 * Driver resolution order inside createDriver():
 *  1. user-registered `extend()` creator
 *  2. `create{Studly}Driver()` method on the subclass
 *  3. throw InvalidArgumentException
 *
 * Existing Phare-specific managers expose domain-shaped selectors
 * (`store/disk/guard/connection/mailer/channel`).  They delegate to
 * {@see driver()} when migrating onto this base, preserving their
 * public API as thin aliases.
 */
abstract class Manager
{
    protected ContainerContract $container;

    /**
     * Resolved configuration values, hydrated from the container's
     * `config` binding when available.  Falls back to an empty array
     * when the container has no config service.
     *
     * @var mixed
     */
    protected mixed $config = [];

    /**
     * Custom driver creators registered via extend().
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * Created driver instance cache keyed by driver name.
     *
     * @var array<string, mixed>
     */
    protected array $drivers = [];

    public function __construct(ContainerContract $container)
    {
        $this->container = $container;

        if ($container->bound('config')) {
            $this->config = $container->make('config');
        }
    }

    /**
     * Get the default driver name.  Null is allowed so subclasses can
     * defer driver decisions when config is missing.
     */
    abstract public function getDefaultDriver(): ?string;

    /**
     * Resolve and cache a driver instance by name.
     */
    public function driver(?string $driver = null): mixed
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if ($driver === null) {
            throw new InvalidArgumentException(
                sprintf('Unable to resolve NULL driver for [%s].', static::class)
            );
        }

        return $this->drivers[$driver] ??= $this->createDriver($driver);
    }

    /**
     * Build a driver, preferring custom creators over the
     * `createXxxDriver` convention method on the subclass.
     */
    protected function createDriver(string $driver): mixed
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $driver)));
        $method = 'create' . $studly . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    protected function callCustomCreator(string $driver): mixed
    {
        return ($this->customCreators[$driver])($this->container);
    }

    /**
     * Register a custom driver creator.  Closure is bound to the
     * manager subclass so it can call protected helpers.
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = Closure::bind($callback, $this, static::class);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    public function getContainer(): ContainerContract
    {
        return $this->container;
    }

    public function setContainer(ContainerContract $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all cached driver instances; future driver() calls
     * rebuild from scratch.
     */
    public function forgetDrivers(): static
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Forward unknown calls to the default driver instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
