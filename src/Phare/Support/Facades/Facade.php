<?php

namespace Phare\Support\Facades;

use Phare\Foundation\AbstractApplication as Application;

abstract class Facade
{
    /**
     * The application instance being facaded.
     *
     * @var Application|null
     */
    protected static $app;

    /**
     * The resolved facade roots that have been explicitly swapped.
     *
     * @var array<string, mixed>
     */
    protected static $resolvedInstance = [];

    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Get the application instance behind the facade.
     *
     * @return \Phare\Support\Facades\Application
     */
    public static function getFacadeApplication()
    {
        return static::$app;
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * Returns null when either the application or the bound service is unavailable
     * so callers can degrade gracefully outside an HTTP context.
     */
    public static function getFacadeRoot(): mixed
    {
        $accessor = static::getFacadeAccessor();
        if (is_object($accessor)) {
            return $accessor;
        }

        if (isset(static::$resolvedInstance[$accessor])) {
            return static::$resolvedInstance[$accessor];
        }

        if (!static::$app) {
            return null;
        }

        try {
            return static::$app->make($accessor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hot-swap the underlying instance behind the facade (also rebinds the
     * container so non-facade resolution sees the same instance).
     */
    public static function swap(mixed $instance): void
    {
        $accessor = static::getFacadeAccessor();
        static::$resolvedInstance[$accessor] = $instance;

        if (static::$app) {
            static::$app->instance($accessor, $instance);
        }
    }

    /**
     * Forget a single swapped facade instance (defaults to this facade's accessor).
     */
    public static function clearResolvedInstance(?string $name = null): void
    {
        $name ??= static::getFacadeAccessor();
        unset(static::$resolvedInstance[$name]);
    }

    /**
     * Forget all swapped facade instances.
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstance = [];
    }

    /**
     * Run a callback when the facade's service is resolved — immediately if it
     * is already bound, otherwise on the next container resolution.
     */
    public static function resolved(\Closure $callback): void
    {
        $accessor = static::getFacadeAccessor();
        if (!static::$app) {
            return;
        }

        if (static::$app->bound($accessor)) {
            $callback(static::getFacadeRoot(), static::$app);
        }

        static::$app->resolving($accessor, function ($service, $app) use ($callback) {
            $callback($service, $app);
        });
    }

    /**
     * Set the application instance.
     *
     * @param \Phare\Support\Facades\Application $app
     * @return void
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $accessor = static::getFacadeAccessor();
        $instance = static::$resolvedInstance[$accessor]
            ?? (static::$app[$accessor] ?? null);

        if (!$instance) {
            throw new \RuntimeException("A facade root has not been set. [$accessor]");
        }

        return $instance->$method(...$args);
    }
}
