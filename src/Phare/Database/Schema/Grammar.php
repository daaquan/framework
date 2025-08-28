<?php

namespace Phare\Database\Schema;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;

abstract class Grammar
{
    protected array $modifiers = [];

    abstract public function compileCreate(Blueprint $blueprint): string;

    abstract public function compileAdd(Blueprint $blueprint): array;

    abstract public function compileDrop(Blueprint $blueprint): string;

    abstract public function compileDropIfExists(Blueprint $blueprint): string;

    abstract public function compileRename(Blueprint $blueprint, string $to): string;

    abstract protected function getType(ColumnDefinition $column): string;

    public function compileBlueprint(Blueprint $blueprint, AbstractPdo $connection): array
    {
        $statements = [];

        if (!$blueprint->isUpdating()) {
            $statements[] = $this->compileCreate($blueprint);
        } else {
            $statements = array_merge($statements, $this->compileAdd($blueprint));
        }

        foreach ($blueprint->getCommands() as $command) {
            $method = 'compile' . ucfirst($command['type']);

            if (method_exists($this, $method)) {
                $sql = $this->$method($blueprint, $command);
                if ($sql) {
                    $statements[] = $sql;
                }
            }
        }

        return $statements;
    }

    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql = $this->wrap($column->getName()) . ' ' . $this->getType($column);
            $sql = $this->addModifiers($sql, $column);
            $columns[] = $sql;
        }

        return $columns;
    }

    protected function addModifiers(string $sql, ColumnDefinition $column): string
    {
        foreach ($this->modifiers as $modifier) {
            $method = "modify{$modifier}";
            if (method_exists($this, $method)) {
                $sql = $this->$method($sql, $column);
            }
        }

        return $sql;
    }

    protected function wrap(string $value): string
    {
        return "`{$value}`";
    }

    protected function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    protected function formatValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_string($value)) {
            return "'{$value}'";
        }

        return (string)$value;
    }

    protected function compilePrimary(Blueprint $blueprint, array $command): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));

        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} ADD PRIMARY KEY ({$columns})";
    }

    protected function compileUnique(Blueprint $blueprint, array $command): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));

        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} ADD UNIQUE {$this->wrap($command['index'])} ({$columns})";
    }

    protected function compileIndex(Blueprint $blueprint, array $command): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));

        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} ADD INDEX {$this->wrap($command['index'])} ({$columns})";
    }

    protected function compileForeign(Blueprint $blueprint, array $command): string
    {
        $foreign = $command['foreign'];
        $table = $this->wrapTable($blueprint->getTable());

        return "ALTER TABLE {$table} ADD CONSTRAINT {$this->wrap($foreign->getName())} " .
               "FOREIGN KEY ({$this->wrap($foreign->getColumn())}) " .
               "REFERENCES {$this->wrapTable($foreign->getTable())} ({$this->wrap($foreign->getReferences())}) " .
               "ON DELETE {$foreign->getOnDelete()} ON UPDATE {$foreign->getOnUpdate()}";
    }

    protected function compileDropColumn(Blueprint $blueprint, array $command): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));

        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} DROP COLUMN {$columns}";
    }

    protected function compileDropPrimary(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} DROP PRIMARY KEY";
    }

    protected function compileDropUnique(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} DROP INDEX {$this->wrap($command['index'])}";
    }

    protected function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} DROP INDEX {$this->wrap($command['index'])}";
    }

    protected function compileDropForeign(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->getTable())} DROP FOREIGN KEY {$this->wrap($command['index'])}";
    }
}
