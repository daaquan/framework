<?php

namespace Phare\Database\Schema;

class ForeignKeyDefinition
{
    protected string $column;

    protected string $table;

    protected string $references;

    protected string $onDelete = 'restrict';

    protected string $onUpdate = 'restrict';

    protected string $name;

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->references = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    public function noActionOnDelete(): self
    {
        return $this->onDelete('no action');
    }

    public function noActionOnUpdate(): self
    {
        return $this->onUpdate('no action');
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getReferences(): string
    {
        return $this->references;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    public function getName(): string
    {
        return $this->name ?? "fk_{$this->column}_{$this->table}_{$this->references}";
    }
}
