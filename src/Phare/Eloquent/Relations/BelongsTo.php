<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

class BelongsTo extends Relation
{
    protected Model $child;

    protected string $foreignKey;

    protected string $ownerKey;

    protected string $relationName;

    public function __construct(Builder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;

        parent::__construct($query, $child);
    }

    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $foreignKey = $this->child->readAttribute($this->foreignKey);

        if ($foreignKey === null) {
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->query->where($this->ownerKey, $foreignKey);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $model->readAttribute($this->foreignKey);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $this->query->whereIn($this->ownerKey, $keys);
    }

    public function getResults(): mixed
    {
        return $this->child->readAttribute($this->foreignKey) === null ? null : $this->query->first();
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    public function match(array $models, iterable $results, string $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->readAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            $key = $model->readAttribute($this->foreignKey);

            if ($key !== null && array_key_exists($key, $dictionary)) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    public function associate(Model|int|string|null $model): Model
    {
        $this->child->{$this->foreignKey} = $model instanceof Model
            ? $model->readAttribute($this->ownerKey)
            : $model;

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } else {
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    public function dissociate(): Model
    {
        $this->child->{$this->foreignKey} = null;
        $this->child->setRelation($this->relationName, null);

        return $this->child;
    }

    public function disassociate(): Model
    {
        return $this->dissociate();
    }

    protected function getRelationType(): int
    {
        return self::BELONGS_TO;
    }

    protected function getRelationFields(): mixed
    {
        return $this->foreignKey;
    }

    protected function getRelatedFields(): mixed
    {
        return $this->ownerKey;
    }
}
