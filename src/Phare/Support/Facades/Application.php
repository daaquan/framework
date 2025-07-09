<?php

namespace Phare\Support\Facades;

/**
 * @method static bool|string environment(...$patterns)
 * @method static string version()
 * @method static void configure($name)
 * @method static string getConfigurationPath($name = null)
 * @method static bool configurationIsCached()
 * @method static void bootstrapWith(array $bootstrappers)
 * @method static bool runningInConsole()
 * @method static bool runningUnitTests()
 * @method static string basePath($path = '')
 * @method static string configPath($path = '')
 * @method static string databasePath($path = '')
 * @method static string storagePath($path = '')
 * @method static string languagePath($path = '')
 * @method static string resourcePath($path = '')
 * @method static string bootstrapPath($path = '')
 */
class Application extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'app';
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
        $instance = static::$app;

        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}
