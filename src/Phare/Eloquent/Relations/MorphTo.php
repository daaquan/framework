<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

class MorphTo extends Relation
{
    protected Model $child;

    protected string $morphType;

    protected string $foreignKey;

    protected string $ownerKey;

    protected string $relationName;

    protected ?string $resolvedRelated = null;

    public function __construct(
        Builder $query,
        Model $child,
        string $morphType,
        string $foreignKey,
        string $ownerKey,
        string $relationName
    ) {
        $this->child = $child;
        $this->morphType = $morphType;
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

        $type = $this->child->readAttribute($this->morphType);
        $id = $this->child->readAttribute($this->foreignKey);

        if ($type === null || $id === null) {
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->query->setModelName($type);
        $this->resolvedRelated = $type;
        $this->query->where($this->ownerKey, $id);
    }

    public function addEagerConstraints(array $models): void
    {
        throw new \RuntimeException('MorphTo eager loading is not implemented.');
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
        return $models;
    }

    public function getResults(): mixed
    {
        $type = $this->child->readAttribute($this->morphType);
        $id = $this->child->readAttribute($this->foreignKey);

        if ($type === null || $id === null) {
            return null;
        }

        $this->query->setModelName($type);
        $this->resolvedRelated = $type;

        return $this->query->where($this->ownerKey, $id)->first();
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
