<?php

namespace Phare\Database;

use Phare\Database\Schema\SchemaBuilder;

abstract class Migration
{
    protected SchemaBuilder $schema;

    public function __construct(SchemaBuilder $schema)
    {
        $this->schema = $schema;
    }

    abstract public function up(): void;

    public function down(): void
    {
        // Optional down method
    }

    protected function table(string $table, \Closure $callback): void
    {
        $this->schema->table($table, $callback);
    }

    protected function create(string $table, \Closure $callback): void
    {
        $this->schema->create($table, $callback);
    }

    protected function dropIfExists(string $table): void
    {
        $this->schema->dropIfExists($table);
    }

    protected function drop(string $table): void
    {
        $this->schema->drop($table);
    }

    protected function rename(string $from, string $to): void
    {
        $this->schema->rename($from, $to);
    }

    protected function hasTable(string $table): bool
    {
        return $this->schema->hasTable($table);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return $this->schema->hasColumn($table, $column);
    }
}
