<?php

namespace Phare\Database;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Contracts\Foundation\Application;

class Migrator
{
    protected Application $app;
    protected AbstractPdo $connection;
    protected SchemaBuilder $schema;
    protected string $table = 'migrations';

    public function __construct(Application $app, AbstractPdo $connection)
    {
        $this->app = $app;
        $this->connection = $connection;
        $this->schema = new SchemaBuilder($connection);
        
        $this->ensureMigrationTable();
    }

    public function run(array $paths = []): array
    {
        $files = $this->getMigrationFiles($paths);
        $ran = [];

        foreach ($files as $file) {
            if (!$this->hasRun($file)) {
                $this->runMigration($file);
                $ran[] = $file;
            }
        }

        return $ran;
    }

    public function rollback(int $steps = 1): array
    {
        $migrations = $this->getLastBatch($steps);
        $rolledBack = [];

        foreach ($migrations as $migration) {
            if ($this->runDown($migration)) {
                $this->removeFromLog($migration);
                $rolledBack[] = $migration;
            }
        }

        return $rolledBack;
    }

    public function reset(): array
    {
        $migrations = $this->getAllRan();
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            if ($this->runDown($migration)) {
                $this->removeFromLog($migration);
                $rolledBack[] = $migration;
            }
        }

        return $rolledBack;
    }

    public function refresh(array $paths = []): array
    {
        $this->reset();
        return $this->run($paths);
    }

    protected function runMigration(string $file): void
    {
        $migration = $this->resolve($file);
        
        try {
            $this->connection->begin();
            $migration->up();
            $this->log($file);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    protected function runDown(string $file): bool
    {
        $migration = $this->resolve($file);
        
        try {
            $this->connection->begin();
            $migration->down();
            $this->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->connection->rollback();
            return false;
        }
    }

    protected function resolve(string $file): Migration
    {
        $class = $this->getMigrationClass($file);
        
        if (!class_exists($class)) {
            require_once $file;
        }

        return new $class($this->schema);
    }

    protected function getMigrationClass(string $file): string
    {
        $name = basename($file, '.php');
        
        // Remove timestamp prefix (e.g., "2023_10_20_000000_create_users_table" -> "create_users_table")
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);
        
        // Convert snake_case to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    protected function getMigrationFiles(array $paths): array
    {
        if (empty($paths)) {
            $paths = [$this->app->databasePath('migrations')];
        }

        $files = [];
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = array_merge($files, glob($path . '/*.php'));
            }
        }

        sort($files);
        return $files;
    }

    protected function hasRun(string $file): bool
    {
        $migration = basename($file, '.php');
        
        return $this->connection->fetchOne(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE migration = ?",
            [$migration]
        )['count'] > 0;
    }

    protected function log(string $file): void
    {
        $migration = basename($file, '.php');
        $batch = $this->getNextBatchNumber();
        
        $this->connection->execute(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$migration, $batch]
        );
    }

    protected function removeFromLog(string $migration): void
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [basename($migration, '.php')]
        );
    }

    protected function getLastBatch(int $steps): array
    {
        $batches = $this->connection->fetchAll(
            "SELECT DISTINCT batch FROM {$this->table} ORDER BY batch DESC LIMIT ?",
            [$steps]
        );

        if (empty($batches)) {
            return [];
        }

        $batchNumbers = array_column($batches, 'batch');
        $placeholders = str_repeat('?,', count($batchNumbers) - 1) . '?';
        
        return array_column(
            $this->connection->fetchAll(
                "SELECT migration FROM {$this->table} WHERE batch IN ({$placeholders}) ORDER BY migration DESC",
                $batchNumbers
            ),
            'migration'
        );
    }

    protected function getAllRan(): array
    {
        return array_column(
            $this->connection->fetchAll(
                "SELECT migration FROM {$this->table} ORDER BY batch ASC, migration ASC"
            ),
            'migration'
        );
    }

    protected function getNextBatchNumber(): int
    {
        $result = $this->connection->fetchOne("SELECT MAX(batch) as max_batch FROM {$this->table}");
        return ($result['max_batch'] ?? 0) + 1;
    }

    protected function ensureMigrationTable(): void
    {
        if (!$this->schema->hasTable($this->table)) {
            $this->schema->create($this->table, function($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamps();
            });
        }
    }
}