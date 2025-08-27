<?php

declare(strict_types=1);

namespace Phare\Foundation;

use Phalcon\Application\AbstractApplication as Application;
use Phalcon\Config\Config;
use Phalcon\Events\Manager;
use Phalcon\Mvc\Micro;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;

/**
 * This abstract class serves as the foundation for all applications built on the framework.
 * It extends the Container class and implements the ApplicationContract interface.
 */
abstract class AbstractApplication extends Container implements ApplicationContract
{
    /**
     * The version of the application.
     */
    final const VERSION = 'dev';

    /**
     * The application instance.
     */
    protected Micro|Application $app;

    /**
     * Indicates if the application has been bootstrapped before.
     */
    protected bool $hasBeenBootstrapped = false;

    /**
     * All the loaded configuration files.
     */
    protected array $loadedConfigurations = [];

    /**
     * The closure to be executed when a route is not found.
     */
    protected ?\Closure $notFoundHandler = null;

    /**
     * Create a new AbstractApplication instance.
     *
     * @param string $basePath The full path to the application directory.
     */
    public function __construct(protected string $basePath)
    {
        self::setDefault($this);

        $this->app = $this->createApplication();

        $this->singleton(ApplicationContract::class, $this);
    }

    /**
     * Create the application instance.
     *
     * @return \Phare\Foundation\Micro|Application
     */
    abstract protected function createApplication();

    /**
     * Handle an incoming request.
     *
     * @param mixed $uri The URI to handle.
     * @return mixed
     */
    abstract public function handle($uri);

    /**
     * Attach an event to the application.
     */
    public function attachEvent($eventType, $handler, $priority = Manager::DEFAULT_PRIORITY)
    {
        $manager = $this->app->getEventsManager() ?? new Manager();
        $manager->attach($eventType, $handler, $priority);

        $this->app->setEventsManager($manager);
    }

    /**
     * Get or check the current application environment.
     *
     * @param mixed $patterns The patterns to check against.
     * @return string|bool
     */
    public function environment(...$patterns)
    {
        $env = getenv('APP_ENV') ?: 'production';

        if (count($patterns) === 0) {
            return $env;
        }

        return in_array($env, $patterns, true);
    }

    /**
     * Load a configuration file.
     *
     * @param string $name The name of the configuration file.
     * @return void
     */
    public function configure($name)
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        $this->loadedConfigurations[$name] = true;

        $path = $this->getConfigurationPath($name);

        if ($path) {
            $config = $this['config'];
            $config->set($name, require $path);
        }
    }

    /**
     * Get the path to the given configuration file.
     *
     * @param string|null $name The name of the configuration file.
     * @return string|null
     */
    public function getConfigurationPath($name = null)
    {
        if (!$name) {
            $appConfigDir = $this->basePath('config') . '/';

            if (file_exists($appConfigDir)) {
                return $appConfigDir;
            }
            if (file_exists($path = __DIR__ . '/../config/')) {
                return $path;
            }
        } else {
            $appConfigPath = $this->basePath('config') . '/' . $name . '.php';

            if (file_exists($appConfigPath)) {
                return $appConfigPath;
            }
            if (file_exists($path = __DIR__ . '/../config/' . $name . '.php')) {
                return $path;
            }
        }
    }

    /**
     * Get the path to the configuration cache file.
     */
    public function getCachedConfigPath(): string
    {
        return $this->bootstrapPath('cache/config.php');
    }

    /**
     * Determine if the configuration has been cached.
     */
    public function configurationIsCached(): bool
    {
        return file_exists($this->getCachedConfigPath());
    }

    public function loadConfiguration(): void
    {
        if (!$this->configurationIsCached()) {
            return;
        }

        $full = require $this->getCachedConfigPath();

        $this['config']->merge($full);
        foreach ($full as $name => $config) {
            $this->loadedConfigurations[$name] = true;
        }
    }

    /**
     * Determine if the routes has been cached.
     */
    public function routesIsCached(): bool
    {
        return file_exists($this->routesCachePath());
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string The full path to the routes cache file.
     */
    public function routesCachePath()
    {
        return $this->bootstrapPath('cache/routes.php');
    }

    /**
     * Register the configured aliases.
     *
     * @return void
     */
    public function registerConfiguredAliases()
    {
        $config = $this['config'];
        $appAliases = $config->path('app.aliases', []);
        $appAliases = is_array($appAliases) ? $appAliases : $appAliases->toArray();
        
        foreach ($appAliases as $alias => $facadeClass) {
            if (class_exists($alias)) {
                continue;
            }

            $facadeClass::setFacadeApplication($this);

            class_alias($facadeClass, $alias);
        }
    }

    /**
     * Register the configured service providers.
     *
     * @return void
     */
    public function registerConfiguredProviders()
    {
        /** @var Config $config */
        $config = $this['config'];

        $appProviders = $config->path('app.providers', []);
        $appProviders = is_array($appProviders) ? $appProviders : $appProviders->toArray();
        
        foreach ($appProviders as $providerClass) {
            (new $providerClass())->register($this);
        }
    }

    /**
     * Bootstrap the application with the given bootstrappers.
     *
     * @param array $bootstrappers The bootstrappers to use.
     * @return void
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->register($this);
        }
    }

    /**
     * Determine if the application has been bootstrapped.
     *
     * @return bool
     */
    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Get the base path for the app base.
     */
    public function basePath(string $path = ''): string
    {
        if (isset($this->basePath)) {
            return $this->basePath . ($path ? '/' . $path : $path);
        }

        if ($this->runningInConsole()) {
            $this->basePath = getcwd();
        } else {
            $this->basePath = dirname(getcwd()) . '/';
        }

        return $this->basePath($path);
    }

    /**
     * Get the bootstrap path.
     *
     * @param string $path The path to append to the bootstrap directory.
     * @return string The full path to the bootstrap directory.
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath("bootstrap/$path");
    }

    /**
     * Get the config path.
     *
     * @param string $path The path to append to the config directory.
     * @return string The full path to the config directory.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath("config/$path");
    }

    /**
     * Get the database path.
     *
     * @param string $path The path to append to the database directory.
     * @return string The full path to the database directory.
     */
    public function databasePath(string $path = ''): string
    {
        return $this->basePath("database/$path");
    }

    /**
     * Get the language path.
     *
     * @param string $path The path to append to the lang directory.
     * @return string The full path to the lang directory.
     */
    public function languagePath(string $path = ''): string
    {
        return $this->basePath("lang/$path");
    }

    /**
     * Get the resource path.
     *
     * @param string $path The path to append to the resources directory.
     * @return string The full path to the resources directory.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath("resources/$path");
    }

    /**
     * Get the storage path.
     *
     * @param string $path The path to append to the storage directory.
     * @return string The full path to the storage directory.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath("storage/$path");
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool True if the application is running in the console, false otherwise.
     */
    public function runningInConsole(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool True if the application is running unit tests, false otherwise.
     */
    public function runningUnitTests(): bool
    {
        return defined('APP_RUNNING_UNIT_TEST') && APP_RUNNING_UNIT_TEST === true;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    abstract public function terminate();

    public function stop()
    {
        if ($this->app instanceof Micro) {
            $this->app->stop();
        }
    }

    /**
     * Get the version of the application.
     *
     * @return string The version of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }
}
