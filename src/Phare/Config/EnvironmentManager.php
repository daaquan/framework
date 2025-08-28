<?php

namespace Phare\Config;

use Phare\Config\EnvironmentDetector;
use Phare\Support\Env;

class EnvironmentManager
{
    protected EnvironmentDetector $detector;

    protected string $environment;

    protected array $environmentFiles = [
        '.env',
        '.env.local',
    ];

    public function __construct(EnvironmentDetector $detector = null)
    {
        $this->detector = $detector ?? new EnvironmentDetector();
    }

    public function detect(string $basePath, callable $environmentCallback = null): string
    {
        $this->environment = $this->detector->detect($environmentCallback);

        $this->loadEnvironmentFiles($basePath);

        return $this->environment;
    }

    public function getEnvironment(): string
    {
        return $this->environment ?? 'production';
    }

    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->getEnvironment(), $environments);
    }

    public function isProduction(): bool
    {
        return $this->isEnvironment('production', 'prod');
    }

    public function isDevelopment(): bool
    {
        return $this->isEnvironment('development', 'dev', 'local');
    }

    public function isTesting(): bool
    {
        return $this->isEnvironment('testing', 'test');
    }

    public function isStaging(): bool
    {
        return $this->isEnvironment('staging', 'stage');
    }

    protected function loadEnvironmentFiles(string $basePath): void
    {
        $files = $this->getEnvironmentFiles();

        foreach ($files as $file) {
            $path = $basePath . '/' . $file;
            if (file_exists($path)) {
                $this->loadEnvironmentFile($path);
            }
        }
    }

    protected function getEnvironmentFiles(): array
    {
        $files = $this->environmentFiles;

        // Add environment-specific files
        $env = $this->getEnvironment();
        if ($env && $env !== 'production') {
            $files[] = ".env.{$env}";
            $files[] = ".env.{$env}.local";
        }

        return $files;
    }

    protected function loadEnvironmentFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                // Always set/override the value (later files take precedence)
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public function setEnvironmentFiles(array $files): void
    {
        $this->environmentFiles = $files;
    }

    public function addEnvironmentFile(string $file): void
    {
        $this->environmentFiles[] = $file;
    }

    public function getDetector(): EnvironmentDetector
    {
        return $this->detector;
    }
}