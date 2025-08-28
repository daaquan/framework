<?php

namespace Phare\Config;

class EnvironmentDetector
{
    protected array $environments = [];

    public function __construct(array $environments = [])
    {
        $this->environments = $environments;
    }

    public function detect(?callable $callback = null): string
    {
        if ($callback) {
            return $callback();
        }

        return $this->detectFromEnvironmentVariable()
            ?? $this->detectFromHostname()
            ?? $this->detectFromArguments()
            ?? 'production';
    }

    protected function detectFromEnvironmentVariable(): ?string
    {
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV');

        return $env ?: null;
    }

    protected function detectFromHostname(): ?string
    {
        $hostname = gethostname();

        foreach ($this->environments as $environment => $hosts) {
            $hosts = is_array($hosts) ? $hosts : [$hosts];

            foreach ($hosts as $host) {
                if ($this->matchesPattern($hostname, $host)) {
                    return $environment;
                }
            }
        }

        return null;
    }

    protected function detectFromArguments(): ?string
    {
        global $argv;

        if (isset($argv)) {
            foreach ($argv as $arg) {
                if (str_starts_with($arg, '--env=')) {
                    return substr($arg, 6);
                }
            }
        }

        return null;
    }

    protected function matchesPattern(string $hostname, string $pattern): bool
    {
        // Exact match
        if ($hostname === $pattern) {
            return true;
        }

        // Wildcard match using fnmatch
        return fnmatch($pattern, $hostname, FNM_CASEFOLD);
    }

    public function setEnvironments(array $environments): void
    {
        $this->environments = $environments;
    }

    public function getEnvironments(): array
    {
        return $this->environments;
    }
}