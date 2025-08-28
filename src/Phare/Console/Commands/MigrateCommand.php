<?php

namespace Phare\Console\Commands;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Console\Command;
use Phare\Database\Migrator;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--fresh : Drop all tables and re-run migrations} {--reset : Rollback all migrations} {--refresh : Reset and re-run all migrations} {--rollback= : Rollback migrations} {--path= : Migration files path}';

    protected string $description = 'Run database migrations';

    public function handle(): int
    {
        $connection = $this->getApplication()->make('db');
        $migrator = new Migrator($this->getApplication(), $connection);

        if ($this->option('fresh')) {
            return $this->runFresh($migrator);
        }

        if ($this->option('reset')) {
            return $this->runReset($migrator);
        }

        if ($this->option('refresh')) {
            return $this->runRefresh($migrator);
        }

        if ($this->option('rollback')) {
            return $this->runRollback($migrator);
        }

        return $this->runMigrations($migrator);
    }

    protected function runMigrations(Migrator $migrator): int
    {
        $paths = $this->getMigrationPaths();

        $this->info('Running migrations...');

        $ran = $migrator->run($paths);

        if (empty($ran)) {
            $this->info('Nothing to migrate.');
        } else {
            $this->info('Migrated:');
            foreach ($ran as $migration) {
                $this->line('  - ' . basename($migration, '.php'));
            }
        }

        return 0;
    }

    protected function runFresh(Migrator $migrator): int
    {
        $this->info('Dropping all tables...');

        // Drop all tables
        $connection = $this->getApplication()->make('db');
        $tables = $this->getAllTables($connection);

        foreach ($tables as $table) {
            $connection->execute("DROP TABLE IF EXISTS {$table}");
        }

        $this->info('Dropped all tables.');

        return $this->runMigrations($migrator);
    }

    protected function runReset(Migrator $migrator): int
    {
        $this->info('Rolling back migrations...');

        $rolledBack = $migrator->reset();

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
        } else {
            $this->info('Rolled back:');
            foreach ($rolledBack as $migration) {
                $this->line('  - ' . $migration);
            }
        }

        return 0;
    }

    protected function runRefresh(Migrator $migrator): int
    {
        $this->runReset($migrator);

        return $this->runMigrations($migrator);
    }

    protected function runRollback(Migrator $migrator): int
    {
        $steps = (int)$this->option('rollback') ?: 1;

        $this->info("Rolling back {$steps} migration(s)...");

        $rolledBack = $migrator->rollback($steps);

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
        } else {
            $this->info('Rolled back:');
            foreach ($rolledBack as $migration) {
                $this->line('  - ' . $migration);
            }
        }

        return 0;
    }

    protected function getMigrationPaths(): array
    {
        $paths = [];

        if ($path = $this->option('path')) {
            $paths[] = $path;
        } else {
            $paths[] = $this->getApplication()->databasePath('migrations');
        }

        return $paths;
    }

    protected function getAllTables(AbstractPdo $connection): array
    {
        $driver = strtolower($connection->getType());

        return match ($driver) {
            'mysql' => array_column(
                $connection->fetchAll('SHOW TABLES'),
                'Tables_in_' . $connection->getDescriptor()['dbname']
            ),
            'sqlite' => array_column(
                $connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table'"),
                'name'
            ),
            'pgsql' => array_column(
                $connection->fetchAll("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"),
                'tablename'
            ),
            default => [],
        };
    }
}
