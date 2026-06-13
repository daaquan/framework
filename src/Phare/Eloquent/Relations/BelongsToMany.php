<?php

namespace Phare\Eloquent\Relations;

use Phalcon\Db\Enum;
use Phare\Collections\Collection;
use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;

class BelongsToMany extends Relation
{
    protected string $table;

    protected string $foreignPivotKey;

    protected string $relatedPivotKey;

    protected string $parentKey;

    protected string $relatedKey;

    protected ?string $relationName;

    protected array $pivotColumns = [];

    public bool $withTimestamps = false;

    protected ?string $pivotCreatedAt = null;

    protected ?string $pivotUpdatedAt = null;

    protected string $accessor = 'pivot';

    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $parentKey = $this->parent->readAttribute($this->parentKey);

        if ($parentKey === null) {
            $this->eagerKeysWereEmpty = true;
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->query->where($this->qualifyPivotColumn($this->foreignPivotKey), $parentKey);
        $this->addBaseConstraints();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $model->readAttribute($this->parentKey);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $this->query->whereIn($this->qualifyPivotColumn($this->foreignPivotKey), $keys);
        $this->addBaseConstraints();
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
            $pivot = $result->getRelation($this->accessor);
            $key = $pivot?->readAttribute($this->foreignPivotKey);

            if ($key !== null) {
                $dictionary[$key][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->readAttribute($this->parentKey);
            $model->setRelation($relation, $this->related->newCollection($dictionary[$key] ?? []));
        }

        return $models;
    }

    public function getResults(): Collection
    {
        $parentKey = $this->parent->readAttribute($this->parentKey);

        if ($parentKey === null) {
            return $this->related->newCollection();
        }

        return $this->get();
    }

    public function get(): Collection
    {
        return $this->hydrateRows($this->fetchRows());
    }

    public function first(): ?Model
    {
        $originalLimit = $this->query->getParams()['limit'] ?? null;

        $this->query->limit(1);

        try {
            return $this->get()->first();
        } finally {
            $this->restoreLimit($originalLimit);
        }
    }

    public function getEager(): iterable
    {
        return $this->eagerKeysWereEmpty ? [] : $this->get();
    }

    public function attach($id, array $attributes = [], $touch = true): void
    {
        $records = $this->formatAttachRecords($this->parseIdsWithAttributes($id, $attributes));

        foreach ($records as $record) {
            $this->parent->getWriteConnection()->insertAsDict($this->table, $record);
        }
    }

    public function detach($ids = null, $touch = true): int
    {
        [$sql, $bindings] = $this->buildPivotDeleteQuery($ids);
        $countSql = preg_replace('/^DELETE/', 'SELECT *', $sql, 1);
        $rows = $this->parent->getReadConnection()->fetchAll($countSql, Enum::FETCH_ASSOC, $bindings);

        if ($rows === []) {
            return 0;
        }

        $this->parent->getWriteConnection()->execute($sql, $bindings);

        return count($rows);
    }

    public function sync($ids, $detaching = true): array
    {
        $records = $this->parseIdsWithAttributes($ids);
        $current = $this->getCurrentPivotMap();
        $attached = [];
        $updated = [];
        $detached = [];

        foreach ($records as $id => $attributes) {
            if (!array_key_exists($id, $current)) {
                $this->attach($id, $attributes, false);
                $attached[] = $id;

                continue;
            }

            if ($attributes !== []) {
                $this->updateExistingPivot($id, $attributes, false);
                $updated[] = $id;
            }
        }

        if ($detaching) {
            $detachIds = array_values(array_diff(array_keys($current), array_keys($records)));

            if ($detachIds !== []) {
                $this->detach($detachIds, false);
                $detached = $detachIds;
            }
        }

        return compact('attached', 'detached', 'updated');
    }

    public function toggle($ids, $touch = true): array
    {
        $records = $this->parseIdsWithAttributes($ids);
        $current = $this->getCurrentPivotMap();
        $attached = [];
        $detached = [];

        foreach ($records as $id => $attributes) {
            if (array_key_exists($id, $current)) {
                $this->detach([$id], false);
                $detached[] = $id;

                continue;
            }

            $this->attach($id, $attributes, false);
            $attached[] = $id;
        }

        return compact('attached', 'detached');
    }

    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        if ($attributes === []) {
            return 0;
        }

        $attributes = $this->addTimestampsToRecord($attributes, false);
        [$sql, $bindings] = $this->buildPivotUpdateQuery($id, $attributes);
        $this->parent->getWriteConnection()->execute($sql, $bindings);

        return 1;
    }

    public function newPivot(array $attributes = [], $exists = false): Pivot
    {
        $pivot = Pivot::fromAttributes($this->parent, $attributes, $this->table, $exists)
            ->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey);

        $pivot->timestamps = $this->withTimestamps;

        return $pivot;
    }

    public function newExistingPivot(array $attributes = []): Pivot
    {
        return $this->newPivot($attributes, true);
    }

    public function withPivot($columns): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->pivotColumns = array_values(array_unique(array_merge($this->pivotColumns, $columns)));

        return $this;
    }

    public function withTimestamps($createdAt = null, $updatedAt = null): static
    {
        $this->withTimestamps = true;
        $this->pivotCreatedAt = $createdAt ?? 'created_at';
        $this->pivotUpdatedAt = $updatedAt ?? 'updated_at';

        return $this->withPivot($this->pivotCreatedAt, $this->pivotUpdatedAt);
    }

    public function as($accessor): static
    {
        $this->accessor = $accessor;

        return $this;
    }

    public function wherePivot($column, $operator = null, $value = null): static
    {
        $this->query->where($this->qualifyPivotColumn((string)$column), $operator, $value);

        return $this;
    }

    public function wherePivotIn($column, $values): static
    {
        $this->query->whereIn($this->qualifyPivotColumn((string)$column), is_array($values) ? $values : [$values]);

        return $this;
    }

    public function orderByPivot($column, $direction = 'asc'): static
    {
        $this->query->orderBy($this->qualifyPivotColumn((string)$column), $direction);

        return $this;
    }

    protected function getRelationType(): int
    {
        return self::HAS_MANY;
    }

    protected function getRelationFields(): mixed
    {
        return $this->parentKey;
    }

    protected function getRelatedFields(): mixed
    {
        return $this->relatedKey;
    }

    protected function qualifyPivotColumn(string $column): string
    {
        return str_contains($column, '.') ? $column : $this->table . '.' . $column;
    }

    protected function addBaseConstraints(): void {}

    protected function fetchRows(): array
    {
        [$sql, $bindings] = $this->buildSelectQuery();

        return $this->parent->getReadConnection()->fetchAll($sql, Enum::FETCH_ASSOC, $bindings);
    }

    protected function buildSelectQuery(): array
    {
        $params = $this->query->getParams();
        $relatedTable = $this->related->getTable();
        $columns = $params['columns'] ?? $relatedTable . '.*';
        $pivotColumns = implode(', ', $this->aliasedPivotColumns());
        $select = $pivotColumns !== '' ? $columns . ', ' . $pivotColumns : $columns;

        $sql = sprintf(
            'SELECT %s FROM %s INNER JOIN %s ON %s = %s',
            $select,
            $relatedTable,
            $this->table,
            $relatedTable . '.' . $this->relatedKey,
            $this->qualifyPivotColumn($this->relatedPivotKey)
        );

        [$conditions, $bindings] = $this->compileConditionsAndBindings(
            (string)($params['conditions'] ?? ''),
            $params['bind'] ?? []
        );

        if ($conditions !== '') {
            $sql .= ' WHERE ' . $conditions;
        }

        // NOTE: group/order are developer-controlled raw SQL fragments (set via
        // groupBy()/orderBy()/orderByRaw()) and are emitted verbatim, mirroring
        // the rest of the query builder. They must never carry untrusted input.
        if (!empty($params['group'])) {
            $sql .= ' GROUP BY ' . $params['group'];
        }

        if (!empty($params['order'])) {
            $sql .= ' ORDER BY ' . $params['order'];
        }

        if (isset($params['limit'])) {
            $limit = $params['limit'];

            if (is_array($limit)) {
                $sql .= sprintf(' LIMIT %d OFFSET %d', (int)$limit['number'], (int)$limit['offset']);
            } else {
                $sql .= ' LIMIT ' . (int)$limit;
            }
        }

        return [$sql, $bindings];
    }

    protected function aliasedPivotColumns(): array
    {
        $columns = array_values(array_unique(array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->pivotColumns
        )));

        return array_map(
            fn (string $column) => sprintf(
                '%s as pivot_%s',
                $this->qualifyPivotColumn($this->validatePivotColumn($column)),
                $this->validatePivotColumn($column)
            ),
            $columns
        );
    }

    /**
     * Reject pivot column identifiers that are not plain `column` or
     * `table.column` names, so they cannot be used to inject SQL through the
     * unquoted SELECT/alias fragments.
     *
     * @throws \InvalidArgumentException
     */
    protected function validatePivotColumn(string $column): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid pivot column identifier [%s].', $column));
        }

        return $column;
    }

    protected function hydrateRows(array $rows): Collection
    {
        $models = [];

        foreach ($rows as $row) {
            $pivotAttributes = [];
            $relatedAttributes = [];

            foreach ($row as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotAttributes[substr($key, 6)] = $value;

                    continue;
                }

                $relatedAttributes[$key] = $value;
            }

            $model = $this->newHydratedRelatedModel($relatedAttributes);
            $model->setRelation($this->accessor, $this->newPivot($pivotAttributes, true));
            $models[] = $model;
        }

        return $this->related->newCollection($models);
    }

    protected function newHydratedRelatedModel(array $attributes): Model
    {
        $class = $this->query->getModelName();
        /** @var Model $model */
        $model = new $class();

        if ($this->parent->getDI() !== null) {
            $model->setDI($this->parent->getDI());
        }

        $model->setRawAttributes($attributes, true);
        $model->markAsRetrieved();

        return $model;
    }

    protected function parseIdsWithAttributes($value, array $attributes = []): array
    {
        if ($value instanceof Model) {
            return [$value->getKey() => $attributes];
        }

        if (!is_array($value)) {
            return [$value => $attributes];
        }

        $records = [];

        foreach ($value as $key => $item) {
            if (is_int($key)) {
                if (is_array($item)) {
                    $records[$key] = $item;

                    continue;
                }

                $records[$item instanceof Model ? $item->getKey() : $item] = [];

                continue;
            }

            $records[$key] = is_array($item) ? $item : [];
        }

        return $records;
    }

    protected function compileConditionsAndBindings(string $conditions, array $bind): array
    {
        if ($conditions === '') {
            return ['', []];
        }

        $bindings = [];
        $usedKeys = [];

        $compiled = preg_replace_callback(
            '/:([A-Za-z0-9_.]+):/',
            function (array $matches) use ($bind, &$bindings, &$usedKeys): string {
                $key = $matches[1];
                $bindings[] = $bind[$key] ?? null;
                $usedKeys[] = $key;

                return '?';
            },
            $conditions
        );

        foreach ($bind as $key => $value) {
            if (is_int($key) || !in_array($key, $usedKeys, true)) {
                $bindings[] = $value;
            }
        }

        return [$compiled ?? $conditions, $bindings];
    }

    protected function formatAttachRecords(array $records): array
    {
        $formatted = [];

        foreach ($records as $id => $attributes) {
            $formatted[] = $this->baseAttachRecord($id, $attributes);
        }

        return $formatted;
    }

    protected function baseAttachRecord(int|string $id, array $attributes = []): array
    {
        $record = array_merge($attributes, [
            $this->foreignPivotKey => $this->parent->readAttribute($this->parentKey),
            $this->relatedPivotKey => $id,
        ]);

        return $this->addTimestampsToRecord($record, true);
    }

    protected function addTimestampsToRecord(array $record, bool $creating): array
    {
        if (!$this->withTimestamps) {
            return $record;
        }

        $timestamp = date('Y-m-d H:i:s');

        if ($creating && $this->pivotCreatedAt !== null) {
            $record[$this->pivotCreatedAt] = $record[$this->pivotCreatedAt] ?? $timestamp;
        }

        if ($this->pivotUpdatedAt !== null) {
            $record[$this->pivotUpdatedAt] = $timestamp;
        }

        return $record;
    }

    protected function buildPivotDeleteQuery($ids = null): array
    {
        $sql = sprintf('DELETE FROM %s WHERE %s = ?', $this->table, $this->foreignPivotKey);
        $bindings = [$this->parent->readAttribute($this->parentKey)];

        if ($ids !== null) {
            $parsed = array_keys($this->parseIdsWithAttributes($ids));
            $placeholders = implode(',', array_fill(0, count($parsed), '?'));
            $sql .= sprintf(' AND %s IN (%s)', $this->relatedPivotKey, $placeholders);
            $bindings = array_merge($bindings, $parsed);
        }

        return $this->applyAdditionalPivotDeleteConstraints($sql, $bindings);
    }

    protected function buildPivotUpdateQuery($id, array $attributes): array
    {
        $assignments = implode(', ', array_map(
            static fn (string $column) => $column . ' = ?',
            array_keys($attributes)
        ));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ? AND %s = ?',
            $this->table,
            $assignments,
            $this->foreignPivotKey,
            $this->relatedPivotKey
        );

        $bindings = array_merge(
            array_values($attributes),
            [$this->parent->readAttribute($this->parentKey), $id]
        );

        return $this->applyAdditionalPivotUpdateConstraints($sql, $bindings);
    }

    protected function applyAdditionalPivotDeleteConstraints(string $sql, array $bindings): array
    {
        return [$sql, $bindings];
    }

    protected function applyAdditionalPivotUpdateConstraints(string $sql, array $bindings): array
    {
        return [$sql, $bindings];
    }

    protected function getCurrentPivotMap(): array
    {
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = ?',
            $this->relatedPivotKey,
            $this->table,
            $this->foreignPivotKey
        );

        $bindings = [$this->parent->readAttribute($this->parentKey)];
        [$sql, $bindings] = $this->applyAdditionalPivotSelectConstraints($sql, $bindings);
        $rows = $this->parent->getReadConnection()->fetchAll($sql, Enum::FETCH_ASSOC, $bindings);
        $map = [];

        foreach ($rows as $row) {
            $map[$row[$this->relatedPivotKey]] = $row;
        }

        return $map;
    }

    protected function applyAdditionalPivotSelectConstraints(string $sql, array $bindings): array
    {
        return [$sql, $bindings];
    }

    protected function restoreLimit(mixed $limit): void
    {
        $params = $this->query->getParams();

        if ($limit === null) {
            unset($params['limit']);
        } else {
            $params['limit'] = $limit;
        }

        (function (array $params): void {
            $this->params = $params;
        })->call($this->query, $params);
    }
}
