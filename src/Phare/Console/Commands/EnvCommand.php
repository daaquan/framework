<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class EnvCommand extends Command
{
    protected string $signature = 'env {--show : Show all environment variables}';

    protected string $description = 'Display the current framework environment';

    public function handle(): int
    {
        $environment = $this->app->environment();

        $this->info("Current environment: <comment>{$environment}</comment>");

        if ($this->option('show')) {
            $this->showEnvironmentVariables();
        }

        return 0;
    }

    protected function showEnvironmentVariables(): void
    {
        $this->line('');
        $this->line('Environment Variables:');
        $this->line('=====================');

        $envVars = $_ENV;
        ksort($envVars);

        $hiddenPatterns = [
            '*_SECRET*',
            '*_KEY*',
            '*_PASSWORD*',
            '*_TOKEN*',
            '*_PRIVATE*',
        ];

        foreach ($envVars as $key => $value) {
            if ($this->shouldHideVariable($key, $hiddenPatterns)) {
                $value = str_repeat('*', min(8, strlen($value)));
            }

            $this->line("<comment>{$key}</comment>=<info>{$value}</info>");
        }
    }

    protected function shouldHideVariable(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}