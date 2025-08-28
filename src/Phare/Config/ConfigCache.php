<?php

namespace Phare\Config;

class ConfigCache
{
    protected string $cachePath;
    
    public function __construct(string $cachePath)
    {
        $this->cachePath = $cachePath;
    }

    /**
     * Cache the configuration to a file.
     */
    public function cache(array $config): void
    {
        $this->ensureCacheDirectoryExists();
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        file_put_contents($this->cachePath, $content, LOCK_EX);
    }

    /**
     * Load the cached configuration.
     */
    public function load(): ?array
    {
        if (!$this->exists()) {
            return null;
        }

        return include $this->cachePath;
    }

    /**
     * Check if the configuration cache exists.
     */
    public function exists(): bool
    {
        return file_exists($this->cachePath);
    }

    /**
     * Clear the configuration cache.
     */
    public function clear(): bool
    {
        if ($this->exists()) {
            return unlink($this->cachePath);
        }

        return true;
    }

    /**
     * Check if the cache is fresh compared to config files.
     */
    public function isFresh(array $configFiles): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $cacheTime = filemtime($this->cachePath);

        foreach ($configFiles as $file) {
            if (file_exists($file) && filemtime($file) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the cache file path.
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Ensure the cache directory exists.
     */
    protected function ensureCacheDirectoryExists(): void
    {
        $directory = dirname($this->cachePath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}