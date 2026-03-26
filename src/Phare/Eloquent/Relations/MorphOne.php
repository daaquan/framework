<?php

namespace Phare\Eloquent\Relations;

use Phare\Eloquent\Builder;

class MorphOne extends MorphMany
{
    protected function getRelationType(): int
    {
        return self::HAS_ONE;
    }

    public function getResults(): mixed
    {
        return $this->getParentKey() === null ? null : $this->query->first();
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
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, array|string $columns = ['*']): Builder
    {
        return $query;
    }
}
