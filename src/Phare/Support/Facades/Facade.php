<?php

namespace Phare\Support\Facades;

use Phare\Foundation\AbstractApplication as Application;

abstract class Facade
{
    /**
     * The application instance being facaded.
     *
     * @var \Phare\Support\Facades\Application
     */
    protected static $app;

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
        if (!static::$app) {
            return null;
        }

        $accessor = static::getFacadeAccessor();
        if (is_object($accessor)) {
            return $accessor;
        }

        try {
            return static::$app->make($accessor);
        } catch (\Throwable) {
            return null;
        }
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
        $instance = static::$app[$accessor] ?? null;

        if (!$instance) {
            throw new \RuntimeException("A facade root has not been set. [$accessor]");
        }

        return $instance->$method(...$args);
    }
}
