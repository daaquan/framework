<?php

namespace Phare\Database\Schema;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Database\Connection;

class SchemaBuilder
{
    protected Connection $connection;

    /**
     * @param Connection|AbstractPdo $connection A raw Phalcon adapter is still
     *                                           accepted for backwards compatibility.
     */
    public function __construct(Connection|AbstractPdo $connection)
    {
        $this->connection = Connection::wrap($connection);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
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
        $this->connection->statement("DROP TABLE {$table}");
    }

    public function dropIfExists(string $table): void
    {
        if ($this->hasTable($table)) {
            $this->drop($table);
        }
    }

    public function rename(string $from, string $to): void
    {
        $driver = $this->getDriverName();

        $sql = match ($driver) {
            'mysql' => "RENAME TABLE {$from} TO {$to}",
            default => "ALTER TABLE {$from} RENAME TO {$to}",
        };

        $this->connection->statement($sql);
    }

    public function hasTable(string $table): bool
    {
        $driver = $this->getDriverName();

        $row = match ($driver) {
            'mysql' => $this->connection->selectOne(
                'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            ),
            'sqlite' => $this->connection->selectOne(
                "SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            ),
            'pgsql' => $this->connection->selectOne(
                "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'",
                [$table]
            ),
            default => null,
        };

        return (int)($row['count'] ?? 0) > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $driver = $this->getDriverName();

        if ($driver === 'sqlite') {
            return in_array($column, $this->getColumnListing($table), true);
        }

        $row = match ($driver) {
            'mysql' => $this->connection->selectOne(
                'SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            ),
            'pgsql' => $this->connection->selectOne(
                "SELECT COUNT(*) as count FROM information_schema.columns WHERE table_name = ? AND column_name = ? AND table_schema = 'public'",
                [$table, $column]
            ),
            default => null,
        };

        return (int)($row['count'] ?? 0) > 0;
    }

    public function getColumnListing(string $table): array
    {
        $driver = $this->getDriverName();

        return match ($driver) {
            'mysql' => array_column(
                $this->connection->select(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position',
                    [$table]
                ),
                'column_name'
            ),
            'sqlite' => array_column($this->connection->select("PRAGMA table_info({$table})"), 'name'),
            'pgsql' => array_column(
                $this->connection->select(
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
            $this->connection->statement($statement);
        }
    }

    protected function getDriverName(): string
    {
        return $this->connection->getDriverName();
    }

    protected function getGrammar(): Grammar
    {
        $driver = $this->getDriverName();

        return match ($driver) {
            'mysql' => new Grammars\MySqlGrammar(),
            'sqlite' => new Grammars\SqliteGrammar(),
            'pgsql' => new Grammars\PostgresGrammar(),
            default => throw new \RuntimeException("Grammar for driver '{$driver}' not supported."),
        };
    }
}
