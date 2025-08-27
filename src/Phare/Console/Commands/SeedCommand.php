<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;
use Phare\Database\Seeder;

class SeedCommand extends Command
{
    protected string $signature = 'db:seed {--class= : The class name of the root seeder} {--force : Force the operation to run when in production}';
    protected string $description = 'Seed the database with records';

    public function handle(): int
    {
        if ($this->getApplication()->environment('production') && !$this->option('force')) {
            $this->error('Seeding is not allowed in production environment. Use --force to override.');
            return 1;
        }

        $class = $this->option('class') ?: 'DatabaseSeeder';
        
        $seederClass = $this->resolveSeederClass($class);
        
        if (!class_exists($seederClass)) {
            $this->error("Seeder class {$seederClass} not found.");
            return 1;
        }

        $this->info("Seeding database...");
        
        try {
            $seeder = new $seederClass($this->getApplication());
            $seeder->run();
            
            $this->info("Database seeded successfully.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Seeding failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function resolveSeederClass(string $class): string
    {
        // If class contains namespace, use as is
        if (str_contains($class, '\\')) {
            return $class;
        }
        
        // Otherwise, assume it's in the Database\Seeders namespace
        return "Database\\Seeders\\{$class}";
    }
}