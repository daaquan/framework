<?php

namespace Phare\Eloquent\Relations;

class HasMany extends HasOneOrMany
{
    protected function getRelationType(): int
    {
        return self::HAS_MANY;
    }

    public function getResults(): mixed
    {
        return $this->getParentKey() === null
            ? $this->related->newCollection()
            : $this->get();
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    public function match(array $models, iterable $results, string $relation): array
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }
}
