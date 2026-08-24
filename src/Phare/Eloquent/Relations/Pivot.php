<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Model;

class Pivot extends Model
{
    public bool $incrementing = false;

    public bool $timestamps = false;

    protected ?string $table = null;

    protected array $guarded = [];

    protected Model $pivotParent;

    protected ?string $foreignKey = null;

    protected ?string $relatedKey = null;

    public static function fromAttributes(Model $parent, array $attributes, string $table, bool $exists = false): static
    {
        $pivot = new static();

        if ($parent->getDI() !== null) {
            $pivot->setDI($parent->getDI());
        }

        $pivot->pivotParent = $parent;
        $pivot->table = $table;
        $pivot->setRawAttributes($attributes, true);
        $pivot->exists = $exists;
        $pivot->changes = [];

        return $pivot;
    }

    public static function fromRawAttributes(Model $parent, array $attributes, string $table, bool $exists = false): static
    {
        return static::fromAttributes($parent, $attributes, $table, $exists);
    }

    public function getTable(): string
    {
        return $this->table ?? parent::getTable();
    }

    public function setPivotKeys(string $foreignKey, string $relatedKey): static
    {
        $this->foreignKey = $foreignKey;
        $this->relatedKey = $relatedKey;

        return $this;
    }

    public function getDeleteQuery(): array
    {
        if ($this->foreignKey === null || $this->relatedKey === null) {
            throw new \RuntimeException('Pivot keys are not set.');
        }

        return [
            'sql' => sprintf(
                'DELETE FROM %s WHERE %s = ? AND %s = ?',
                $this->getTable(),
                $this->foreignKey,
                $this->relatedKey
            ),
            'bindings' => [
                $this->readAttribute($this->foreignKey),
                $this->readAttribute($this->relatedKey),
            ],
        ];
    }

    public function delete(): bool
    {
        $query = $this->getDeleteQuery();

        $deleted = $this->getQueryConnection()->statement($query['sql'], $query['bindings']);

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted;
    }

    public function getForeignKey(): string
    {
        if ($this->foreignKey === null) {
            throw new \RuntimeException('Pivot foreign key is not set.');
        }

        return $this->foreignKey;
    }

    public function getRelatedKey(): string
    {
        if ($this->relatedKey === null) {
            throw new \RuntimeException('Pivot related key is not set.');
        }

        return $this->relatedKey;
    }
}
