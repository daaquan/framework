<?php

namespace Phare\Eloquent\Relations;

use Phare\Collections\Collection;
use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

/**
 * Base for Phare's relations.
 *
 * This used to extend Phalcon\Mvc\Model\Relation purely to hand it the
 * relation metadata in its constructor; nothing ever read that metadata back.
 * The metadata is kept here instead, and the Phalcon inheritance is gone.
 */
abstract class Relation
{
    // Same values as Phalcon\Mvc\Model\Relation used, so stored or compared
    // relation types keep their meaning across the change.
    public const BELONGS_TO = 0;

    public const HAS_ONE = 1;

    public const HAS_MANY = 2;

    public const HAS_ONE_THROUGH = 3;

    public const HAS_MANY_THROUGH = 4;

    public const NO_ACTION = 0;

    public const ACTION_RESTRICT = 1;

    public const ACTION_CASCADE = 2;

    protected static bool $constraints = true;

    protected int $type;

    protected mixed $fields;

    protected mixed $referencedFields;

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

        $this->type = $this->getRelationType();
        $this->fields = $this->getRelationFields();
        $this->referencedFields = $this->getRelatedFields();

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

    public function getType(): int
    {
        return $this->type;
    }

    public function getFields(): mixed
    {
        return $this->fields;
    }

    public function getReferencedFields(): mixed
    {
        return $this->referencedFields;
    }

    public function getReferencedModel(): ?string
    {
        return $this->query->getModelName();
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
