<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

abstract class HasOneOrMany extends Relation
{
    protected string $foreignKey;

    protected string $localKey;

    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $parentKey = $this->getParentKey();

        if ($parentKey === null) {
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->query->where($this->foreignKey, $parentKey);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $model->readAttribute($this->localKey);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $this->query->whereIn($this->foreignKey, $keys);
    }

    public function save(Model $model): Model|false
    {
        $this->setForeignAttributesForCreate($model);

        return $model->save() ? $model : false;
    }

    public function create(array $attributes = []): Model|false
    {
        $class = $this->query->getModelName();
        /** @var Model $model */
        $model = new $class();

        if ($this->parent->getDI() !== null) {
            $model->setDI($this->parent->getDI());
        }

        $model->fill($attributes);

        return $this->save($model);
    }

    protected function matchOneOrMany(array $models, iterable $results, string $relation, string $type): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->readAttribute($this->foreignKey)][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->readAttribute($this->localKey);
            $matches = $dictionary[$key] ?? [];

            $model->setRelation(
                $relation,
                $type === 'one'
                    ? ($matches[0] ?? null)
                    : $this->related->newCollection($matches)
            );
        }

        return $models;
    }

    protected function getParentKey(): mixed
    {
        return $this->parent->readAttribute($this->localKey);
    }

    protected function setForeignAttributesForCreate(Model $model): void
    {
        $model->{$this->foreignKey} = $this->getParentKey();
    }

    protected function getRelationFields(): mixed
    {
        return $this->localKey;
    }

    protected function getRelatedFields(): mixed
    {
        return $this->foreignKey;
    }
}
