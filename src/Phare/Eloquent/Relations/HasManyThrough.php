<?php

namespace Phare\Eloquent\Relations;

class HasManyThrough extends HasOneOrManyThrough
{
    protected function getRelationType(): int
    {
        return self::HAS_MANY;
    }

    public function getResults(): mixed
    {
        return $this->getParentKey() === null
            ? $this->related->newCollection()
            : $this->getThrough();
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
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->{self::THROUGH_KEY_ALIAS} ?? null;

            if ($key !== null) {
                $dictionary[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->readAttribute($this->localKey);

            if ($key !== null) {
                $model->setRelation($relation, $this->related->newCollection($dictionary[$key] ?? []));
            }
        }

        return $models;
    }
}
