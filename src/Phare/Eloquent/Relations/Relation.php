<?php

namespace Phare\Eloquent\Relations;

use Phalcon\Mvc\Model\Relation as PhalconRelation;
use Phare\Collections\Collection;
use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

abstract class Relation extends PhalconRelation
{
    protected static bool $constraints = true;

    protected Builder $query;

    protected Model $parent;

    protected Model $related;

    protected bool $eagerKeysWereEmpty = false;

    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = new ($query->getModelName())();

        if ($parent->getDI() !== null) {
            $this->related->setDI($parent->getDI());
        }

        parent::__construct(
            $this->getRelationType(),
            $query->getModelName(),
            $this->getRelationFields(),
            $this->getRelatedFields()
        );

        $this->addConstraints();
    }

    public static function noConstraints(\Closure $callback): mixed
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    abstract public function addConstraints(): void;

    abstract protected function getRelationType(): int;

    abstract protected function getRelationFields(): mixed;

    abstract protected function getRelatedFields(): mixed;

    abstract public function addEagerConstraints(array $models): void;

    abstract public function initRelation(array $models, string $relation): array;

    abstract public function match(array $models, iterable $results, string $relation): array;

    abstract public function getResults(): mixed;

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, array|string $columns = ['*']): Builder
    {
        return $query;
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getRelated(): Model
    {
        return $this->related;
    }

    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->qualifyColumn($this->parent->getKeyName());
    }

    public function get(): Collection
    {
        return new Collection(iterator_to_array($this->query->get(), false));
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }

    public function getEager(): iterable
    {
        return $this->eagerKeysWereEmpty ? [] : $this->query->get();
    }

    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->query->$method(...$arguments);

        return $result === $this->query ? $this : $result;
    }
}
