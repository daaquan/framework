<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;
use Phare\Support\Facades\Cache;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'cache:clear', description: 'Flush the application cache.')]
class CacheClearCommand extends Command
{
    public function handle(): void
    {
        try {
            // Clear application cache
            Cache::flush();

            $this->info('Application cache cleared!');

            // Clear cache-related files if they exist
            $cacheFiles = [
                base_path('bootstrap/cache/compiled.php'),
                base_path('bootstrap/cache/packages.php'),
                base_path('bootstrap/cache/services.php'),
            ];

            $clearedFiles = 0;
            foreach ($cacheFiles as $file) {
                if (file_exists($file) && unlink($file)) {
                    $clearedFiles++;
                }
            }

            if ($clearedFiles > 0) {
                $this->info("Cleared {$clearedFiles} cache files.");
            }

        } catch (\Throwable $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            exit(1);
        }
    }
}