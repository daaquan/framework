<?php

namespace Phare\Eloquent\Relations;

use Phare\Collections\Collection;
use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

abstract class HasOneOrManyThrough extends Relation
{
    protected const THROUGH_KEY_ALIAS = '__phare_through_key';

    protected Model $throughParent;

    protected Model $farParent;

    protected string $firstKey;

    protected string $secondKey;

    protected string $localKey;

    protected string $secondLocalKey;

    public function __construct(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey
    ) {
        $this->farParent = $farParent;
        $this->throughParent = $throughParent;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->localKey = $localKey;
        $this->secondLocalKey = $secondLocalKey;

        parent::__construct($query, $farParent);
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

        $this->query->where($this->getQualifiedFirstKeyName(), $parentKey);
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

        $this->query->whereIn($this->getQualifiedFirstKeyName(), $keys);
    }

    public function getEager(): iterable
    {
        if ($this->eagerKeysWereEmpty) {
            return [];
        }

        return iterator_to_array($this->getThrough(), false);
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, array|string $columns = ['*']): Builder
    {
        return $query;
    }

    protected function getThrough(): Collection
    {
        [$sql, $bind] = $this->compileSqlAndBindings();

        $rows = $this->related->getQueryConnection()->select($sql, $bind);

        $models = array_map(fn (array $row) => $this->hydrateRow($row), $rows);

        return $this->query->eagerLoadModels($models);
    }

    protected function getParentKey(): mixed
    {
        return $this->farParent->readAttribute($this->localKey);
    }

    protected function getQualifiedFirstKeyName(): string
    {
        return $this->throughParent->qualifyColumn($this->firstKey);
    }

    public function getQualifiedParentKeyName(): string
    {
        return $this->throughParent->qualifyColumn($this->secondLocalKey);
    }

    protected function getQualifiedFarKeyName(): string
    {
        return $this->related->qualifyColumn($this->secondKey);
    }

    protected function getRelationFields(): mixed
    {
        return $this->localKey;
    }

    protected function getRelatedFields(): mixed
    {
        return $this->secondKey;
    }

    protected function toSql(): string
    {
        $relatedTable = $this->related->getTable();
        $throughTable = $this->throughParent->getTable();
        $params = $this->query->getParams();

        $sql = sprintf(
            'SELECT %s.*, %s.%s AS %s FROM %s INNER JOIN %s ON %s = %s',
            $relatedTable,
            $throughTable,
            $this->firstKey,
            self::THROUGH_KEY_ALIAS,
            $relatedTable,
            $throughTable,
            $this->getQualifiedParentKeyName(),
            $this->getQualifiedFarKeyName()
        );

        if (!empty($params['conditions'])) {
            $sql .= ' WHERE ' . $params['conditions'];
        }

        if (!empty($params['order'])) {
            $sql .= ' ORDER BY ' . $params['order'];
        }

        if (isset($params['limit'])) {
            $sql .= $this->compileLimit($params['limit']);
        }

        return $sql;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function compileSqlAndBindings(): array
    {
        $sql = preg_replace('/:([a-zA-Z0-9_]+):/', ':$1', $this->toSql()) ?? $this->toSql();

        return [$sql, $this->query->getParams()['bind'] ?? []];
    }

    protected function compileLimit(mixed $limit): string
    {
        if (is_array($limit)) {
            return sprintf(' LIMIT %d OFFSET %d', $limit['number'], $limit['offset'] ?? 0);
        }

        return sprintf(' LIMIT %d', $limit);
    }

    protected function hydrateRow(array $row): Model
    {
        $throughKey = $row[self::THROUGH_KEY_ALIAS] ?? null;
        unset($row[self::THROUGH_KEY_ALIAS]);

        $class = $this->related::class;

        /** @var Model $model */
        $model = new $class();

        if ($this->farParent->getDI() !== null) {
            $model->setDI($this->farParent->getDI());
        }

        $model->hydrate($row);
        $model->{self::THROUGH_KEY_ALIAS} = $throughKey;

        return $model;
    }
}
