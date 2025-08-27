<?php

namespace Phare\Database\Schema;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];
    protected bool $updating = false;

    public function __construct(string $table, bool $updating = false)
    {
        $this->table = $table;
        $this->updating = $updating;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function isUpdating(): bool
    {
        return $this->updating;
    }

    // Column types
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigIncrements', $column);
    }

    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn('increments', $column);
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, ['length' => $length]);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, ['precision' => $precision, 'scale' => $scale]);
    }

    public function float(string $column, int $precision = 53): ColumnDefinition
    {
        return $this->addColumn('float', $column, ['precision' => $precision]);
    }

    public function double(string $column): ColumnDefinition
    {
        return $this->addColumn('double', $column);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column);
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    // Foreign keys
    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($column);
        $this->commands[] = ['type' => 'foreign', 'foreign' => $foreign];
        return $foreign;
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->bigInteger($column)->unsigned();
    }

    public function foreignIdFor(string $model, string $column = null): ColumnDefinition
    {
        if ($column === null) {
            $column = strtolower(class_basename($model)) . '_id';
        }
        return $this->foreignId($column);
    }

    // Indexes
    public function primary(array|string $columns): void
    {
        $this->indexCommand('primary', $columns);
    }

    public function unique(array|string $columns, string $name = null): void
    {
        $this->indexCommand('unique', $columns, $name);
    }

    public function index(array|string $columns, string $name = null): void
    {
        $this->indexCommand('index', $columns, $name);
    }

    public function fulltext(array|string $columns, string $name = null): void
    {
        $this->indexCommand('fulltext', $columns, $name);
    }

    // Drop operations
    public function dropColumn(array|string $columns): void
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->commands[] = ['type' => 'dropColumn', 'columns' => $columns];
    }

    public function dropPrimary(): void
    {
        $this->commands[] = ['type' => 'dropPrimary'];
    }

    public function dropUnique(string $index): void
    {
        $this->commands[] = ['type' => 'dropUnique', 'index' => $index];
    }

    public function dropIndex(string $index): void
    {
        $this->commands[] = ['type' => 'dropIndex', 'index' => $index];
    }

    public function dropForeign(string $index): void
    {
        $this->commands[] = ['type' => 'dropForeign', 'index' => $index];
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];
    }

    // Helper methods
    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition($type, $name, $parameters);
        $this->columns[] = $column;
        return $column;
    }

    protected function indexCommand(string $type, array|string $columns, string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->commands[] = [
            'type' => $type,
            'columns' => $columns,
            'index' => $name ?: $this->createIndexName($type, $columns)
        ];
    }

    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }

    public function toSql(AbstractPdo $connection, Grammar $grammar): array
    {
        return $grammar->compileBlueprint($this, $connection);
    }
}