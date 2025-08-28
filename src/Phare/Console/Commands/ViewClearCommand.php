<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'view:clear', description: 'Clear all compiled view files.')]
class ViewClearCommand extends Command
{
    public function handle(): void
    {
        $viewCachePath = storage_path('cache/views');

        if (!is_dir($viewCachePath)) {
            $this->info('View cache directory does not exist.');
            return;
        }

        $clearedCount = 0;
        $files = glob($viewCachePath . '/*');

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $clearedCount++;
            }
        }

        if ($clearedCount > 0) {
            $this->info("Cleared {$clearedCount} compiled view files!");
        } else {
            $this->info('No compiled view files found.');
        }
    }
}