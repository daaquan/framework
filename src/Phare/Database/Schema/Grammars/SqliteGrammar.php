<?php

namespace Phare\Database\Schema\Grammars;

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\ColumnDefinition;
use Phare\Database\Schema\Grammar;

class SqliteGrammar extends Grammar
{
    protected array $modifiers = [
        'Nullable', 'Default', 'Increment',
    ];

    public function compileCreate(Blueprint $blueprint): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = implode(', ', $this->getColumns($blueprint));

        return "CREATE TABLE {$table} ({$columns})";
    }

    public function compileAdd(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $statements = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql = $this->wrap($column->getName()) . ' ' . $this->getType($column);
            $sql = $this->addModifiers($sql, $column);
            $statements[] = "ALTER TABLE {$table} ADD COLUMN {$sql}";
        }

        return $statements;
    }

    public function compileDrop(Blueprint $blueprint): string
    {
        return "DROP TABLE {$this->wrapTable($blueprint->getTable())}";
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return "DROP TABLE IF EXISTS {$this->wrapTable($blueprint->getTable())}";
    }

    public function compileRename(Blueprint $blueprint, string $to): string
    {
        $from = $this->wrapTable($blueprint->getTable());
        $to = $this->wrapTable($to);

        return "ALTER TABLE {$from} RENAME TO {$to}";
    }

    protected function getType(ColumnDefinition $column): string
    {
        return match ($column->getType()) {
            'bigIncrements', 'increments' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'string' => 'VARCHAR(' . ($column->getAttributes()['length'] ?? 255) . ')',
            'text', 'longText' => 'TEXT',
            'integer', 'bigInteger' => 'INTEGER',
            'decimal' => 'DECIMAL(' . ($column->getAttributes()['precision'] ?? 8) . ',' . ($column->getAttributes()['scale'] ?? 2) . ')',
            'float', 'double' => 'REAL',
            'boolean' => 'INTEGER',
            'date', 'dateTime', 'timestamp' => 'TEXT',
            'json' => 'TEXT',
            'binary' => 'BLOB',
            'enum' => 'TEXT CHECK(' . $this->wrap($column->getName()) . ' IN (' . implode(',', array_map(fn ($v) => "'{$v}'", $column->getAttributes()['values'] ?? [])) . '))',
            default => 'TEXT',
        };
    }

    protected function modifyNullable(string $sql, ColumnDefinition $column): string
    {
        if ($column->getAttributes()['nullable'] ?? false) {
            return $sql;
        }

        return $sql . ' NOT NULL';
    }

    protected function modifyDefault(string $sql, ColumnDefinition $column): string
    {
        if (array_key_exists('default', $column->getAttributes())) {
            $default = $column->getAttributes()['default'];

            if ($column->getAttributes()['useCurrent'] ?? false) {
                return $sql . ' DEFAULT CURRENT_TIMESTAMP';
            }

            return $sql . ' DEFAULT ' . $this->formatValue($default);
        }

        return $sql;
    }

    protected function modifyIncrement(string $sql, ColumnDefinition $column): string
    {
        // SQLite handles AUTO_INCREMENT in the type itself
        return $sql;
    }

    protected function wrap(string $value): string
    {
        return "\"{$value}\"";
    }

    protected function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    // SQLite doesn't support adding foreign keys after table creation
    protected function compileForeign(Blueprint $blueprint, array $command): string
    {
        return '';
    }

    // SQLite has limited ALTER TABLE support
    protected function compileDropColumn(Blueprint $blueprint, array $command): string
    {
        return ''; // Not supported in SQLite
    }

    protected function compileDropPrimary(Blueprint $blueprint, array $command): string
    {
        return ''; // Not supported in SQLite
    }

    protected function compileDropUnique(Blueprint $blueprint, array $command): string
    {
        return "DROP INDEX IF EXISTS {$this->wrap($command['index'])}";
    }

    protected function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        return "DROP INDEX IF EXISTS {$this->wrap($command['index'])}";
    }

    protected function compileDropForeign(Blueprint $blueprint, array $command): string
    {
        return ''; // Not supported in SQLite
    }
}
