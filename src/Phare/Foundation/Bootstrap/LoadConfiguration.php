<?php

namespace Phare\Foundation\Bootstrap;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Config\Repository;
use Phare\Foundation\AbstractApplication as Application;

/**
 * Load various configuration settings.
 */
class LoadConfiguration implements ServiceProviderInterface
{
    private ?string $compiledFilePath = null;

    /**
     * Prepare the configuration cache and load it into the application.
     */
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('config', Repository::class);

        $this->compiledFilePath = $app->getCachedConfigPath();

        if ($app->configurationIsCached()) {
            if ($app->environment('local', 'testing') && $this->isConfigOutdated($app)) {
                // During development regenerate the cache when configuration files change
                $this->generateConfigurationCacheFile($app);
            }
        } else {
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
        (new \Phare\Bootstrap\LoadEnvironmentVariables())
            ->bootstrap($app);

        $configs = ['@timestamp' => $this->getConfigFilesModificationTime($app)];
        foreach (glob($app->configPath() . '/*.php') as $configFile) {
            $configFileName = pathinfo($configFile)['filename'];
            $configs[$configFileName] = require $configFile;
        }

        $configContent = '<?php return ' . var_export($configs, true) . ';';
        file_put_contents($this->compiledFilePath, $configContent);
    }

    /**
     * Get the last modification time of the configuration files.
     */
    protected function getConfigFilesModificationTime(Application $app): int
    {
        $configFiles = glob($app->configPath() . '/*.php');

        $lastModificationTime = 0;
        foreach ($configFiles as $configFile) {
            $lastModificationTime = max($lastModificationTime, filemtime($configFile));
        }

        // Also check the last modified time of the .env file
        $lastModificationTime = max($lastModificationTime, filemtime($app->basePath('.env')));

        $envConfigFile = $app->getCachedConfigPath();
        if (file_exists($envConfigFile)) {
            return max($lastModificationTime, filemtime($envConfigFile));
        }

        return $lastModificationTime;
    }
}
