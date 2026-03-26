<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class EnvCommand extends Command
{
    protected ?string $signature = 'env {--show : Show all environment variables}';

    protected ?string $description = 'Display the current framework environment';

    public function handle(): int
    {
        $environment = $this->getFrameworkApplication()->environment();

        if ($this->isJsonMode()) {
            $data = ['environment' => $environment];

            if ($this->option('show')) {
                $data['variables'] = $this->collectEnvironmentVariables();
            }

            $this->agentSuccess("Environment: {$environment}", $data);
        } else {
            $this->info("Current environment: <comment>{$environment}</comment>");

            if ($this->option('show')) {
                $this->renderEnvironmentVariables();
            }
        }

        return 0;
    }

    protected function collectEnvironmentVariables(): array
    {
        $envVars = $_ENV;
        ksort($envVars);
        $out = [];

        foreach ($envVars as $key => $value) {
            $out[$key] = $this->shouldHideVariable($key) ? str_repeat('*', min(8, strlen($value))) : $value;
        }

        return $out;
    }

    protected function renderEnvironmentVariables(): void
    {
        $this->line('');
        $this->line('Environment Variables:');
        $this->line('=====================');

        foreach ($this->collectEnvironmentVariables() as $key => $value) {
            $this->line("<comment>{$key}</comment>=<info>{$value}</info>");
        }
    }

    protected function shouldHideVariable(string $key): bool
    {
        foreach (['*_SECRET*', '*_KEY*', '*_PASSWORD*', '*_TOKEN*', '*_PRIVATE*'] as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}
