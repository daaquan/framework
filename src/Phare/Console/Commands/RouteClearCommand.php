<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'route:clear', description: 'Remove the route cache file.')]
class RouteClearCommand extends Command
{
    public function handle(): void
    {
        $cacheFile = base_path('bootstrap/cache/routes.php');

        if (file_exists($cacheFile)) {
            if (unlink($cacheFile)) {
                $this->info('Route cache cleared!');
            } else {
                $this->error('Failed to clear route cache.');
                exit(1);
            }
        } else {
            $this->info('Route cache does not exist.');
        }
    }
}