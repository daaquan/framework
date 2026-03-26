<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

class MorphToMany extends BelongsToMany
{
    protected string $morphType;

    protected string $morphClass;

    protected bool $inverse;

    public function __construct(
        Builder $query,
        Model $parent,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null,
        bool $inverse = false
    ) {
        $this->inverse = $inverse;
        $this->morphType = $name . '_type';
        $this->morphClass = $inverse
            ? ($query->getEloquentModel()?->getMorphClass() ?? $query->getModelName())
            : $parent->getMorphClass();

        parent::__construct(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );
    }

    protected function addBaseConstraints(): void
    {
        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
    }

    public function newPivot(array $attributes = [], $exists = false): Pivot
    {
        $pivot = MorphPivot::fromAttributes(
            $this->parent,
            array_merge([$this->morphType => $this->morphClass], $attributes),
            $this->table,
            $exists
        )->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey);

        $pivot->timestamps = $this->withTimestamps;

        if ($pivot instanceof MorphPivot) {
            $pivot->setMorphType($this->morphType)->setMorphClass($this->morphClass);
        }

        return $pivot;
    }

    protected function baseAttachRecord(int|string $id, array $attributes = []): array
    {
        return array_merge(
            parent::baseAttachRecord($id, $attributes),
            [$this->morphType => $this->morphClass]
        );
    }

    protected function aliasedPivotColumns(): array
    {
        $this->withPivot($this->morphType);

        return parent::aliasedPivotColumns();
    }

    protected function applyAdditionalPivotDeleteConstraints(string $sql, array $bindings): array
    {
        $sql .= sprintf(' AND %s = ?', $this->morphType);
        $bindings[] = $this->morphClass;

        return [$sql, $bindings];
    }

    protected function applyAdditionalPivotUpdateConstraints(string $sql, array $bindings): array
    {
        $sql .= sprintf(' AND %s = ?', $this->morphType);
        $bindings[] = $this->morphClass;

        return [$sql, $bindings];
    }

    protected function applyAdditionalPivotSelectConstraints(string $sql, array $bindings): array
    {
        $sql .= sprintf(' AND %s = ?', $this->morphType);
        $bindings[] = $this->morphClass;

        return [$sql, $bindings];
    }
}
