<?php

namespace Phare\Database\Schema;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;

class SchemaBuilder
{
    protected AbstractPdo $connection;

    public function __construct(AbstractPdo $connection)
    {
        $this->connection = $connection;
    }

    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $this->build($blueprint);
    }

    public function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        
        $this->build($blueprint);
    }

    public function drop(string $table): void
    {
        $this->connection->execute("DROP TABLE {$table}");
    }

    public function dropIfExists(string $table): void
    {
        if ($this->hasTable($table)) {
            $this->drop($table);
        }
    }

    public function rename(string $from, string $to): void
    {
        $this->connection->execute("RENAME TABLE {$from} TO {$to}");
    }

    public function hasTable(string $table): bool
    {
        $driver = $this->getDriverName();
        
        return match($driver) {
            'mysql' => $this->connection->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            )['count'] > 0,
            'sqlite' => $this->connection->fetchOne(
                "SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            )['count'] > 0,
            'pgsql' => $this->connection->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'",
                [$table]
            )['count'] > 0,
            default => false,
        };
    }

    public function hasColumn(string $table, string $column): bool
    {
        $driver = $this->getDriverName();
        
        return match($driver) {
            'mysql' => $this->connection->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
                [$table, $column]
            )['count'] > 0,
            'sqlite' => !empty($this->connection->fetchAll(
                "PRAGMA table_info({$table})"
            )) && in_array($column, array_column($this->connection->fetchAll("PRAGMA table_info({$table})"), 'name')),
            'pgsql' => $this->connection->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.columns WHERE table_name = ? AND column_name = ? AND table_schema = 'public'",
                [$table, $column]
            )['count'] > 0,
            default => false,
        };
    }

    public function getColumnListing(string $table): array
    {
        $driver = $this->getDriverName();
        
        return match($driver) {
            'mysql' => array_column(
                $this->connection->fetchAll(
                    "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position",
                    [$table]
                ),
                'column_name'
            ),
            'sqlite' => array_column($this->connection->fetchAll("PRAGMA table_info({$table})"), 'name'),
            'pgsql' => array_column(
                $this->connection->fetchAll(
                    "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public' ORDER BY ordinal_position",
                    [$table]
                ),
                'column_name'
            ),
            default => [],
        };
    }

    protected function build(Blueprint $blueprint): void
    {
        $statements = $blueprint->toSql($this->connection, $this->getGrammar());
        
        foreach ($statements as $statement) {
            $this->connection->execute($statement);
        }
    }

    protected function getDriverName(): string
    {
        return strtolower($this->connection->getType());
    }

    protected function getGrammar(): Grammar
    {
        $driver = $this->getDriverName();
        
        return match($driver) {
            'mysql' => new Grammars\MySqlGrammar(),
            'sqlite' => new Grammars\SqliteGrammar(),
            'pgsql' => new Grammars\PostgresGrammar(),
            default => throw new \RuntimeException("Grammar for driver '{$driver}' not supported."),
        };
    }
}