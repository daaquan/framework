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

    /**
     * @var array<string, array<string, array<int, Model>>>
     */
    protected array $dictionary = [];

    /**
     * @var array<int, Model>
     */
    protected array $models = [];

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

        $related = $this->resolveRelatedClass($type);
        $this->query->setModelName($related);
        $this->query->setEloquentModel($this->newRelatedInstance($related));
        $this->resolvedRelated = $related;
        $this->query->where($this->ownerKey, $id);
    }

    public function addEagerConstraints(array $models): void
    {
        $this->models = $models;
        $this->dictionary = [];

        foreach ($models as $model) {
            $type = $model->readAttribute($this->morphType);
            $id = $model->readAttribute($this->foreignKey);

            if ($type === null || $id === null) {
                continue;
            }

            $this->dictionary[(string)$type][(string)$id][] = $model;
        }
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

        $related = $this->resolveRelatedClass($type);

        $this->query->setModelName($related);
        $this->query->setEloquentModel($this->newRelatedInstance($related));
        $this->resolvedRelated = $related;

        return $this->query->where($this->ownerKey, $id)->first();
    }

    public function getEager(): iterable
    {
        foreach (array_keys($this->dictionary) as $type) {
            $class = $this->resolveRelatedClass($type);
            $results = $this->getResultsByType($type, $class);

            foreach ($results as $result) {
                $ownerKey = $result->readAttribute($this->ownerKey);

                if ($ownerKey === null) {
                    continue;
                }

                foreach ($this->dictionary[$type][(string)$ownerKey] ?? [] as $model) {
                    $model->setRelation($this->relationName, $result);
                }
            }
        }

        return $this->models;
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

    protected function resolveRelatedClass(string $type): string
    {
        $resolved = Model::getActualClassNameForMorph($type);

        if (!class_exists($resolved)) {
            throw new \RuntimeException(sprintf(
                'Unable to resolve morph type [%s] for relationship [%s].',
                $type,
                $this->relationName
            ));
        }

        return $resolved;
    }

    protected function newRelatedInstance(string $related): Model
    {
        /** @var Model $instance */
        $instance = new $related();

        if ($this->child->getDI() !== null) {
            $instance->setDI($this->child->getDI());
        }

        return $instance;
    }

    protected function getResultsByType(string $type, string $class): iterable
    {
        $instance = $this->newRelatedInstance($class);
        $query = clone $this->query;

        $query->setModelName($class);
        $query->setEloquentModel($instance);

        $keys = array_keys($this->dictionary[$type]);

        if ($keys === []) {
            return [];
        }

        return $query->whereIn($this->ownerKey, $keys)->get();
    }
}
