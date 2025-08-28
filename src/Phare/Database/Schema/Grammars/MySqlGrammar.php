<?php

namespace Phare\Database\Schema\Grammars;

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\ColumnDefinition;
use Phare\Database\Schema\Grammar;

class MySqlGrammar extends Grammar
{
    protected array $modifiers = [
        'Unsigned', 'Charset', 'Collate', 'Nullable', 'Default', 'Increment', 'Comment', 'After', 'First',
    ];

    protected array $serialCommands = ['create', 'add'];

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

        return "RENAME TABLE {$from} TO {$to}";
    }

    protected function getType(ColumnDefinition $column): string
    {
        return match ($column->getType()) {
            'bigIncrements' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'increments' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'string' => 'VARCHAR(' . ($column->getAttributes()['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'longText' => 'LONGTEXT',
            'integer' => 'INT',
            'bigInteger' => 'BIGINT',
            'decimal' => 'DECIMAL(' . ($column->getAttributes()['precision'] ?? 8) . ',' . ($column->getAttributes()['scale'] ?? 2) . ')',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'boolean' => 'TINYINT(1)',
            'date' => 'DATE',
            'dateTime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'json' => 'JSON',
            'binary' => 'BLOB',
            'enum' => 'ENUM(' . implode(',', array_map(fn ($v) => "'{$v}'", $column->getAttributes()['values'] ?? [])) . ')',
            default => 'VARCHAR(255)',
        };
    }

    protected function modifyUnsigned(string $sql, ColumnDefinition $column): string
    {
        if ($column->getAttributes()['unsigned'] ?? false) {
            return $sql . ' UNSIGNED';
        }

        return $sql;
    }

    protected function modifyCharset(string $sql, ColumnDefinition $column): string
    {
        if ($charset = $column->getAttributes()['charset'] ?? null) {
            return $sql . ' CHARACTER SET ' . $charset;
        }

        return $sql;
    }

    protected function modifyCollate(string $sql, ColumnDefinition $column): string
    {
        if ($collation = $column->getAttributes()['collation'] ?? null) {
            return $sql . ' COLLATE ' . $collation;
        }

        return $sql;
    }

    protected function modifyNullable(string $sql, ColumnDefinition $column): string
    {
        if ($column->getAttributes()['nullable'] ?? false) {
            return $sql . ' NULL';
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
        if ($column->getAttributes()['autoIncrement'] ?? false) {
            return $sql . ' AUTO_INCREMENT';
        }

        return $sql;
    }

    protected function modifyComment(string $sql, ColumnDefinition $column): string
    {
        if ($comment = $column->getAttributes()['comment'] ?? null) {
            return $sql . " COMMENT '{$comment}'";
        }

        return $sql;
    }

    protected function modifyAfter(string $sql, ColumnDefinition $column): string
    {
        if ($after = $column->getAttributes()['after'] ?? null) {
            return $sql . ' AFTER ' . $this->wrap($after);
        }

        return $sql;
    }

    protected function modifyFirst(string $sql, ColumnDefinition $column): string
    {
        if ($column->getAttributes()['first'] ?? false) {
            return $sql . ' FIRST';
        }

        return $sql;
    }
}
