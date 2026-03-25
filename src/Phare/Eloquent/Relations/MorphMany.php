<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

class MorphMany extends HasMany
{
    protected string $morphType;

    protected string $morphClass;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $localKey,
        string $morphType,
        string $morphClass
    ) {
        $this->morphType = $morphType;
        $this->morphClass = $morphClass;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    public function addConstraints(): void
    {
        parent::addConstraints();

        if (static::$constraints) {
            $this->query->where($this->morphType, $this->morphClass);
        }
    }

    public function addEagerConstraints(array $models): void
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->morphType, $this->morphClass);
    }

    protected function setForeignAttributesForCreate(Model $model): void
    {
        parent::setForeignAttributesForCreate($model);
        $model->{$this->morphType} = $this->morphClass;
    }
}
