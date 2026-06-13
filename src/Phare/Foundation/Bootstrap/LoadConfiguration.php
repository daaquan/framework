<?php

namespace Phare\Foundation\Bootstrap;

use Phalcon\Config\Config;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\AbstractApplication as Application;

/**
 * Load various configuration settings.
 */
class LoadConfiguration implements ServiceProviderInterface
{
    private ?string $compiledFilePath = null;

    /**
     * Laravel-style bootstrap entry point.
     */
    public function bootstrap(Application|DiInterface $app): void
    {
        $this->register($app);
    }

    /**
     * Prepare the configuration cache and load it into the application.
     */
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('config', Config::class);

        $this->compiledFilePath = $app->getCachedConfigPath();

        if (!$app->configurationIsCached()) {
            $this->generateConfigurationCacheFile($app);
        } elseif ($app->runningUnitTests() || $app->environment('testing')) {
            $this->generateConfigurationCacheFile($app);
        } elseif ($app->environment('local') && $this->isConfigOutdated($app)) {
            $this->generateConfigurationCacheFile($app);
        }

        // Load the configuration
        $app->loadConfiguration();
    }

    /**
     * Determine whether the cache is outdated.
     *
     * @param Application|DiInterface $app
     */
    private function isConfigOutdated(Application $app): bool
    {
        $cachedConfigs = require $this->compiledFilePath;
        $lastModificationTime = $this->getConfigFilesModificationTime($app);

        return $lastModificationTime !== ($cachedConfigs['@timestamp'] ?? 0);
    }

    /**
     * Generate the configuration cache file.
     */
    protected function generateConfigurationCacheFile(Application $app): void
    {
        (new LoadEnvironmentVariables())
            ->bootstrap($app);

        $configs = ['@timestamp' => $this->getConfigFilesModificationTime($app)];
        foreach (glob($app->configPath() . '/*.php') ?: [] as $configFile) {
            $configFileName = pathinfo($configFile)['filename'];
            $configs[$configFileName] = require $configFile;
        }

        $configContent = '<?php return ' . var_export($configs, true) . ';';
        $cacheDir = dirname($this->compiledFilePath);
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new \RuntimeException("Unable to create config cache directory: {$cacheDir}");
        }
        file_put_contents($this->compiledFilePath, $configContent);
    }

    /**
     * Get the last modification time of the configuration files.
     */
    protected function getConfigFilesModificationTime(Application $app): int
    {
        $configFiles = glob($app->configPath() . '/*.php') ?: [];

        $lastModificationTime = 0;
        foreach ($configFiles as $configFile) {
            $lastModificationTime = max($lastModificationTime, filemtime($configFile));
        }

        // Also check the last modified time of the .env file (if present)
        $envFile = $app->basePath('.env');
        if (file_exists($envFile)) {
            $lastModificationTime = max($lastModificationTime, filemtime($envFile));
        }

        return $lastModificationTime;
    }
}
