<?php

namespace Phare\Database;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Contracts\Foundation\Application;
use Phare\Database\Schema\SchemaBuilder;

class Migrator
{
    protected Application $app;

    protected Connection $connection;

    protected SchemaBuilder $schema;

    protected string $table = 'migrations';

    protected array $paths = [];

    /**
     * @param Connection|AbstractPdo $connection A raw Phalcon adapter is still
     *                                           accepted for backwards compatibility.
     */
    public function __construct(Application $app, Connection|AbstractPdo $connection)
    {
        $this->app = $app;
        $this->connection = Connection::wrap($connection);
        $this->schema = new SchemaBuilder($this->connection);

        $this->ensureMigrationTable();
    }

    public function run(array $paths = []): array
    {
        $this->paths = array_merge($this->paths, $paths);
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
            $file = $this->resolveFile($migration);
            if ($file && $this->runDown($file)) {
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
            $file = $this->resolveFile($migration);
            if ($file && $this->runDown($file)) {
                $this->removeFromLog($migration);
                $rolledBack[] = $migration;
            }
        }

        return $rolledBack;
    }

    protected function resolveFile(string $migrationName): ?string
    {
        $allPaths = $this->paths ?: [$this->app->databasePath('migrations')];

        foreach ($allPaths as $path) {
            $file = $path . '/' . $migrationName . '.php';
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    public function refresh(array $paths = []): array
    {
        if (!empty($paths)) {
            $this->paths = array_merge($this->paths, $paths);
        }
        $this->reset();

        return $this->run($paths);
    }

    protected function runMigration(string $file): void
    {
        $migration = $this->resolve($file);

        try {
            $this->connection->beginTransaction();
            $migration->up();
            $this->log($file);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    protected function runDown(string $file): bool
    {
        $migration = $this->resolve($file);

        try {
            $this->connection->beginTransaction();
            $migration->down();
            $this->connection->commit();

            return true;
        } catch (\Exception $e) {
            $this->connection->rollBack();

            return false;
        }
    }

    protected function resolve(string $file): Migration
    {
        $result = require $file;

        // Support anonymous class migrations (return new class extends Migration)
        if ($result instanceof Migration) {
            $result->setSchema($this->schema);

            return $result;
        }

        $class = $this->getMigrationClass($file);

        if (!class_exists($class)) {
            throw new \RuntimeException("Migration class {$class} not found in {$file}");
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

        $row = $this->connection->selectOne(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE migration = ?",
            [$migration]
        );

        return (int)($row['count'] ?? 0) > 0;
    }

    protected function log(string $file): void
    {
        $migration = basename($file, '.php');
        $batch = $this->getNextBatchNumber();

        $this->connection->statement(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$migration, $batch]
        );
    }

    protected function removeFromLog(string $migration): void
    {
        $this->connection->statement(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [basename($migration, '.php')]
        );
    }

    protected function getLastBatch(int $steps): array
    {
        $batches = $this->connection->select(
            "SELECT DISTINCT batch FROM {$this->table} ORDER BY batch DESC LIMIT ?",
            [$steps]
        );

        if (empty($batches)) {
            return [];
        }

        $batchNumbers = array_column($batches, 'batch');
        $placeholders = str_repeat('?,', count($batchNumbers) - 1) . '?';

        return array_column(
            $this->connection->select(
                "SELECT migration FROM {$this->table} WHERE batch IN ({$placeholders}) ORDER BY migration DESC",
                $batchNumbers
            ),
            'migration'
        );
    }

    protected function getAllRan(): array
    {
        return array_column(
            $this->connection->select(
                "SELECT migration FROM {$this->table} ORDER BY batch ASC, migration ASC"
            ),
            'migration'
        );
    }

    protected function getNextBatchNumber(): int
    {
        $result = $this->connection->selectOne("SELECT MAX(batch) as max_batch FROM {$this->table}");

        return (int)($result['max_batch'] ?? 0) + 1;
    }

    protected function ensureMigrationTable(): void
    {
        if (!$this->schema->hasTable($this->table)) {
            $this->schema->create($this->table, function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamps();
            });
        }
    }
}
