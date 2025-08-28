<?php

namespace Phare\Config;

class ConfigEnvironment
{
    protected string $environment;

    protected array $configs = [];

    protected array $environmentOverrides = [];

    public function __construct(string $environment)
    {
        $this->environment = $environment;
    }

    public function load(string $configPath): array
    {
        $configs = $this->loadBaseConfigs($configPath);

        $environmentConfigs = $this->loadEnvironmentConfigs($configPath);

        return $this->mergeConfigs($configs, $environmentConfigs);
    }

    protected function loadBaseConfigs(string $configPath): array
    {
        $configs = [];
        $configFiles = glob($configPath . '/*.php');

        foreach ($configFiles as $file) {
            $key = basename($file, '.php');
            
            // Skip environment-specific files
            if (str_contains($key, '.')) {
                continue;
            }

            $configs[$key] = require $file;
        }

        return $configs;
    }

    protected function loadEnvironmentConfigs(string $configPath): array
    {
        $configs = [];
        $pattern = $configPath . '/*.{env}.php';
        $envPattern = str_replace('{env}', $this->environment, $pattern);
        
        $environmentFiles = glob($envPattern);

        foreach ($environmentFiles as $file) {
            $filename = basename($file, '.php');
            $parts = explode('.', $filename);
            
            if (count($parts) >= 2) {
                $key = $parts[0];
                $configs[$key] = require $file;
            }
        }

        return $configs;
    }

    protected function mergeConfigs(array $baseConfigs, array $environmentConfigs): array
    {
        $merged = $baseConfigs;

        foreach ($environmentConfigs as $key => $config) {
            if (isset($merged[$key]) && is_array($merged[$key]) && is_array($config)) {
                $merged[$key] = $this->mergeConfigArrays($merged[$key], $config);
            } else {
                $merged[$key] = $config;
            }
        }

        return $merged;
    }

    protected function mergeConfigArrays(array $base, array $override): array
    {
        $result = $base;
        
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeConfigArrays($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironmentOverride(string $key, mixed $value): void
    {
        $this->environmentOverrides[$key] = $value;
    }

    public function getEnvironmentOverrides(): array
    {
        return $this->environmentOverrides;
    }

    public function hasEnvironmentOverride(string $key): bool
    {
        return array_key_exists($key, $this->environmentOverrides);
    }

    public function removeEnvironmentOverride(string $key): void
    {
        unset($this->environmentOverrides[$key]);
    }

    public static function createFromDetector(string $configPath, ?string $environment = null): self
    {
        $detector = new EnvironmentDetector();
        $environment = $environment ?? $detector->detect();
        
        return new static($environment);
    }
}