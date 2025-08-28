<?php

namespace Phare\Console\Commands;

use Phare\Config\ConfigCache;
use Phare\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'config:clear', description: 'Remove the configuration cache file.')]
class ConfigClearCommand extends Command
{
    public function handle(): void
    {
        $cacheFile = base_path('bootstrap/cache/config.php');
        $configCache = new ConfigCache($cacheFile);

        if ($configCache->clear()) {
            $this->info('Configuration cache cleared!');
        } else {
            $this->info('Configuration cache does not exist or could not be cleared.');
        }
    }
}