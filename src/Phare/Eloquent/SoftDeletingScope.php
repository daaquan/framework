<?php

namespace Phare\Eloquent;

class SoftDeletingScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withTrashed', function (Builder $builder, bool $withTrashed = true) {
            if (!$withTrashed) {
                return $builder->withoutTrashed();
            }

            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getEloquentModel();

            return $builder
                ->withoutGlobalScope($this)
                ->whereNotNull($model?->getQualifiedDeletedAtColumn() ?? 'deleted_at');
        });

        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getEloquentModel();

            return $builder
                ->withoutGlobalScope($this)
                ->whereNull($model?->getQualifiedDeletedAtColumn() ?? 'deleted_at');
        });

        $builder->macro('restore', function (Builder $builder) {
            $models = iterator_to_array($builder->withTrashed()->get(), false);

            return $this->runDeleteStateUpdate($models, null);
        });

        $builder->macro('forceDelete', function (Builder $builder) {
            $models = iterator_to_array($builder->withTrashed()->get(), false);

            return $this->runForceDelete($models);
        });
    }

    /**
     * @param array<int, Model> $models
     */
    protected function runDeleteStateUpdate(array $models, mixed $value): int
    {
        if ($models === []) {
            return 0;
        }

        $model = $models[0];
        $ids = $this->modelKeys($models, $model->getKeyName());

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $model->getWriteConnection()->execute(
            sprintf(
                'UPDATE %s SET %s = ? WHERE %s IN (%s)',
                $model->getTable(),
                $model->getDeletedAtColumn(),
                $model->getKeyName(),
                $placeholders
            ),
            array_merge([$value], $ids)
        );

        return count($ids);
    }

    /**
     * @param array<int, Model> $models
     */
    protected function runForceDelete(array $models): int
    {
        if ($models === []) {
            return 0;
        }

        $model = $models[0];
        $ids = $this->modelKeys($models, $model->getKeyName());

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $model->getWriteConnection()->execute(
            sprintf(
                'DELETE FROM %s WHERE %s IN (%s)',
                $model->getTable(),
                $model->getKeyName(),
                $placeholders
            ),
            $ids
        );

        return count($ids);
    }

    /**
     * @param array<int, Model> $models
     * @return array<int, mixed>
     */
    protected function modelKeys(array $models, string $keyName): array
    {
        return array_values(array_filter(
            array_map(static fn (Model $model) => $model->readAttribute($keyName), $models),
            static fn ($id) => $id !== null
        ));
    }
}
