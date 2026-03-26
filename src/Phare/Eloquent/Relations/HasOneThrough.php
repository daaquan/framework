<?php

namespace Phare\Eloquent\Relations;

class HasOneThrough extends HasOneOrManyThrough
{
    protected function getRelationType(): int
    {
        return self::HAS_ONE;
    }

    public function getResults(): mixed
    {
        return $this->getParentKey() === null ? null : $this->getThrough()->first();
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
            $key = $result->{self::THROUGH_KEY_ALIAS} ?? null;

            if ($key !== null && !array_key_exists($key, $dictionary)) {
                $dictionary[$key] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->readAttribute($this->localKey);

            if ($key !== null && array_key_exists($key, $dictionary)) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }
}
