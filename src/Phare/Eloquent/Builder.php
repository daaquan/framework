<?php

namespace Phare\Eloquent;

use Closure;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\ModelInterface;
use Phare\Collections\Collection;
use Phare\Eloquent\Relations\Relation;
use Phare\Pagination\LengthAwarePaginator;
use Phare\Pagination\Paginator;

/**
 * Eloquent Builder for Phalcon
 */
class Builder extends Criteria implements BuilderInterface
{
    /**
     * Auto-incrementing counter to generate unique bind parameter keys.
     */
    private int $bindIndex = 0;

    /**
     * @var array<string, Closure|null>
     */
    private array $eagerLoad = [];

    /**
     * @var array<string, Scope|Closure>
     */
    protected array $scopes = [];

    /**
     * @var array<int, string>
     */
    protected array $removedScopes = [];

    /**
     * @var array<string, Closure>
     */
    protected array $macros = [];

    protected ?Model $eloquentModel = null;

    protected bool $scopesApplied = false;

    public function setModel(Model $model): static
    {
        $this->eloquentModel = $model;
        parent::setModelName($model::class);

        return $this;
    }

    public function setEloquentModel(Model $model): static
    {
        $this->eloquentModel = $model;

        return $this;
    }

    public function getEloquentModel(): ?Model
    {
        if ($this->eloquentModel instanceof Model) {
            return $this->eloquentModel;
        }

        $modelName = $this->getModelName();

        if (is_string($modelName) && class_exists($modelName) && is_subclass_of($modelName, Model::class)) {
            /** @var Model $model */
            $model = new $modelName();
            $this->eloquentModel = $model;

            return $model;
        }

        return null;
    }

    public function withGlobalScope($identifier, $scope): static
    {
        $this->scopes[$identifier] = $scope;

        if (is_object($scope) && method_exists($scope, 'extend')) {
            $scope->extend($this);
        }

        return $this;
    }

    public function withoutGlobalScope($scope): BuilderInterface
    {
        $identifier = $this->resolveScopeIdentifier($scope);

        unset($this->scopes[$identifier]);
        $this->removedScopes[] = $identifier;

        return $this;
    }

    public function withoutGlobalScopes($scopes = null): BuilderInterface
    {
        $scopes ??= array_keys($this->scopes);

        foreach ((array)$scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function removedScopes(): array
    {
        return $this->removedScopes;
    }

    public function macro(string $name, Closure $macro): static
    {
        $this->macros[$name] = $macro;

        return $this;
    }

    public function applyScopes(): static
    {
        if ($this->scopes === [] || $this->scopesApplied) {
            return $this;
        }

        $this->scopesApplied = true;

        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($this->scopes[$identifier])) {
                continue;
            }

            if ($scope instanceof Closure) {
                $scope($this);

                continue;
            }

            $scope->apply($this, $this->getEloquentModel() ?? new ($this->getModelName())());
        }

        return $this;
    }

    /**
     * Get the first result of the query.
     */
    public function first(): ?ModelInterface
    {
        $results = $this->applyScopes()->get();

        return $results instanceof Collection ? $results->first() : $results->getFirst();
    }

    /**
     * Get the last result of the query.
     */
    public function last(): ?ModelInterface
    {
        $results = $this->get();

        return $results instanceof Collection ? $results->last() : $results->getLast();
    }

    /**
     * Execute the query and return the result set.
     */
    public function get(): ResultsetInterface|Collection
    {
        $builder = $this->applyScopes();
        $modelName = $builder->getModelName();
        $params = $builder->getParams();

        $results = is_string($modelName) && method_exists($modelName, 'rawFind')
            ? $modelName::rawFind($params)
            : $builder->execute();

        if ($this->eagerLoad !== []) {
            return $this->eagerLoadRelations($results);
        }

        return $results;
    }

    /**
     * Convert the given condition to the Phalcon format.
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     * @return array
     */
    private function phalconCondition($field, $operator = null, $value = null)
    {
        if ($field instanceof Closure) {
            // Seed the sub-builder's bind counter from the parent so the keys it
            // generates (bind_N, field_N) never collide with the parent's keys
            // when the two bind arrays are merged.
            $builder = new self();
            $builder->bindIndex = $this->bindIndex;
            $field($builder);

            // Advance the parent past every key the sub-builder consumed.
            $this->bindIndex = $builder->bindIndex;

            $params = $builder->getParams();

            return [
                'conditions' => $params['conditions'],
                'bind' => $params['bind'],
            ];
        }

        if ($operator === null && $value === null) {
            return [
                'conditions' => $field,
                'bind' => [],
            ];
        }

        if (is_array($operator) && $value === null) {
            return $this->compilePositionalCondition($field, $operator);
        }

        if ($value === null && !$this->isOperator($operator)) {
            $value = $operator;
            $operator = '=';
        }

        $bindKey = preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$field) . '_' . $this->bindIndex++;

        return [
            'conditions' => "$field $operator :$bindKey:",
            'bind' => [$bindKey => $value],
        ];
    }

    private function compilePositionalCondition(string $condition, array $values): array
    {
        $bind = [];

        foreach (array_values($values) as $index => $value) {
            $bindKey = 'bind_' . $this->bindIndex++;
            $condition = preg_replace('/\?/', ':' . $bindKey . ':', $condition, 1);
            $bind[$bindKey] = $value;
        }

        return [
            'conditions' => $condition,
            'bind' => $bind,
        ];
    }

    private function isOperator(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtoupper($value), [
            '=',
            '!=',
            '<>',
            '>',
            '<',
            '>=',
            '<=',
            'LIKE',
            'NOT LIKE',
            'IS',
            'IS NOT',
        ], true);
    }

    public function with($relations, $callback = null): BuilderInterface
    {
        $relations = is_string($relations) && $callback !== null
            ? [$relations => $callback]
            : (is_string($relations) ? [$relations] : $relations);

        foreach ($this->parseWithRelations($relations) as $name => $constraints) {
            $this->eagerLoad[$name] = $this->combineConstraints(
                $this->eagerLoad[$name] ?? null,
                $constraints
            );
        }

        return $this;
    }

    private function eagerLoadRelations(ResultsetInterface $results): Collection
    {
        $models = iterator_to_array($results, false);

        return $this->eagerLoadModels($models);
    }

    public function eagerLoadModels(array $models): Collection
    {
        if ($models === []) {
            return new Collection();
        }

        foreach ($this->eagerLoad as $name => $constraints) {
            $this->eagerLoadRelation($models, $name, $constraints);
        }

        return new Collection($models);
    }

    private function eagerLoadRelation(array $models, string $name, ?Closure $constraints): void
    {
        $relation = Relation::noConstraints(
            fn () => $models[0]->$name()
        );

        if (!$relation instanceof Relation) {
            throw new \RuntimeException(sprintf(
                'Relationship [%s] on model [%s] must return a relation instance.',
                $name,
                $this->getModelName()
            ));
        }

        $relation->addEagerConstraints($models);

        if ($constraints !== null) {
            $constraints($relation);
        }

        $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(),
            $name
        );
    }

    /**
     * @param array<int|string, mixed> $relations
     * @return array<string, Closure|null>
     */
    private function parseWithRelations(array $relations): array
    {
        $parsed = [];

        foreach ($relations as $name => $constraints) {
            if (is_int($name)) {
                $name = (string)$constraints;
                $constraints = null;
            }

            $this->addNestedWithRelation(
                $parsed,
                (string)$name,
                $constraints instanceof Closure ? $constraints : null
            );
        }

        return $parsed;
    }

    /**
     * @param array<string, Closure|null> $parsed
     */
    private function addNestedWithRelation(array &$parsed, string $name, ?Closure $constraints): void
    {
        $segments = explode('.', $name);
        $topLevel = array_shift($segments);

        if ($topLevel === null || $topLevel === '') {
            return;
        }

        if ($segments === []) {
            $parsed[$topLevel] = $this->combineConstraints($parsed[$topLevel] ?? null, $constraints);

            return;
        }

        $nested = implode('.', $segments);

        $parsed[$topLevel] = $this->combineConstraints(
            $parsed[$topLevel] ?? null,
            function ($query) use ($nested, $constraints) {
                $query->with($nested, $constraints);
            }
        );
    }

    private function combineConstraints(?Closure $first, ?Closure $second): ?Closure
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return function ($query) use ($first, $second) {
            $first($query);
            $second($query);
        };
    }

    /**
     * Update the matching models.
     *
     * Each matching model is loaded and the attributes are applied through
     * {@see Model::setAttribute()} so casts and mutators run, then persisted
     * via {@see Model::update()} which touches `updated_at` (when the model
     * uses timestamps) and fires the saving/updating/updated/saved events.
     * This is slower than a single bulk statement but keeps full parity with
     * per-model behaviour rather than issuing a raw UPDATE that bypasses it.
     *
     * @param array<string, mixed> $attributes
     * @return int The number of models updated.
     */
    public function update(array $attributes): int
    {
        $models = iterator_to_array($this->applyScopes()->get(), false);

        if ($models === []) {
            return 0;
        }

        $updated = 0;

        /** @var Model $model */
        foreach ($models as $model) {
            foreach ($attributes as $column => $value) {
                $model->setAttribute((string)$column, $value);
            }

            if ($model->update()) {
                $updated++;
            }
        }

        return $updated;
    }

    public function delete(): int
    {
        $deleted = 0;

        foreach ($this->applyScopes()->get() as $model) {
            if ($model->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Add a basic where clause.
     * Usage:
     * $builder->where('name', 'John') or $builder->where('name', '=', 'John')
     * $builder->where('price', '>', 100)
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     */
    public function where($field, $operator = null, $value = null): BuilderInterface
    {
        $params = $this->phalconCondition($field, $operator, $value);

        if (empty($this->params['conditions'])) {
            $this->params['conditions'] = $params['conditions'];
        } else {
            $this->params['conditions'] = "({$this->params['conditions']}) AND ({$params['conditions']})";
        }

        $this->params['bind'] = array_merge($this->params['bind'] ?? [], $params['bind'] ?? []);

        return $this;
    }

    /**
     * Add an AND condition.
     * Usage: $builder->andWhere('age', '>', 18)
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     */
    public function andWhere($field, $operator = null, $value = null): BuilderInterface
    {
        return $this->where($field, $operator, $value);
    }

    /**
     * Add an OR condition.
     * Usage: $builder->orWhere('name', 'John')
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     */
    public function orWhere($field, $operator = null, $value = null): BuilderInterface
    {
        $params = $this->phalconCondition($field, $operator, $value);

        if (empty($this->params['conditions'])) {
            $this->params['conditions'] = $params['conditions'];
        } else {
            $this->params['conditions'] = "({$this->params['conditions']}) OR ({$params['conditions']})";
        }

        $this->params['bind'] = array_merge($this->params['bind'] ?? [], $params['bind'] ?? []);

        return $this;
    }

    /**
     * Add an IN condition.
     * Usage: $builder->whereIn('id', [1, 2, 3])
     *
     * @param mixed $field
     */
    public function whereIn($field, array $values): BuilderInterface
    {
        if ($values === []) {
            return $this->where('0 = 1');
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        return $this->where("$field IN ($placeholders)", $values);
    }

    /**
     * Add an OR IN condition.
     * Usage: $builder->orWhereIn('id', [4, 5, 6])
     *
     * @param mixed $field
     */
    public function orWhereIn($field, array $values): BuilderInterface
    {
        if ($values === []) {
            return $this->orWhere('0 = 1');
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        return $this->orWhere("$field IN ($placeholders)", $values);
    }

    /**
     * Add a BETWEEN condition.
     * Usage: $builder->whereBetween('age', 20, 30)
     *
     * @param mixed $field
     * @param mixed $min
     * @param mixed $max
     */
    public function whereNotIn($field, array $values): BuilderInterface
    {
        if ($values === []) {
            return $this->where('1 = 1');
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        return $this->where("$field NOT IN ($placeholders)", $values);
    }

    /**
     * Add a BETWEEN condition.
     * Usage: $builder->whereBetween('age', 20, 30)
     *
     * @param mixed $field
     * @param mixed $min
     * @param mixed $max
     */
    public function whereBetween($field, $min, $max): BuilderInterface
    {
        return $this->where("$field BETWEEN ? AND ?", [$min, $max]);
    }

    /**
     * Add a NOT BETWEEN condition.
     * Usage: $builder->whereNotBetween('age', 15, 19)
     *
     * @param mixed $field
     * @param mixed $min
     * @param mixed $max
     */
    public function whereNotBetween($field, $min, $max): BuilderInterface
    {
        return $this->where("$field NOT BETWEEN ? AND ?", [$min, $max]);
    }

    /**
     * Add a NULL condition.
     * Usage: $builder->whereNull('deleted_at')
     *
     * @param mixed $field
     */
    public function whereNull($field): BuilderInterface
    {
        return $this->where("$field IS NULL");
    }

    /**
     * Add a NOT NULL condition.
     * Usage: $builder->whereNotNull('deleted_at')
     *
     * @param mixed $field
     */
    public function whereNotNull($field): BuilderInterface
    {
        return $this->where("$field IS NOT NULL");
    }

    /**
     * Add a LIKE condition.
     * Usage: $builder->whereLike('name', '%John%')
     *
     * @param mixed $field
     * @param mixed $value
     */
    public function whereLike($field, $value): BuilderInterface
    {
        return $this->where("$field LIKE ?", $value);
    }

    /**
     * Add a NOT LIKE condition.
     * Usage: $builder->whereNotLike('name', '%John%')
     *
     * @param mixed $field
     * @param mixed $value
     */
    public function whereNotLike($field, $value): BuilderInterface
    {
        return $this->where("$field NOT LIKE ?", $value);
    }

    /**
     * Add a raw condition directly.
     * Usage: $builder->whereRaw('count > ?', [10])
     *
     * @param string $conditions
     */
    public function whereRaw($conditions, array $bind = []): BuilderInterface
    {
        if (empty($this->params['conditions'])) {
            $this->params['conditions'] = $conditions;
        } else {
            $this->params['conditions'] = "({$this->params['conditions']}) AND ({$conditions})";
        }

        $this->params['bind'] = array_merge($this->params['bind'] ?? [], $bind);

        return $this;
    }

    /**
     * Add a raw OR condition.
     * Usage: $builder->orWhereRaw('count < ?', [5])
     *
     * @param string $conditions
     */
    public function orWhereRaw($conditions, array $bind = []): BuilderInterface
    {
        if (empty($this->params['conditions'])) {
            $this->params['conditions'] = $conditions;
        } else {
            $this->params['conditions'] = "({$this->params['conditions']}) OR ({$conditions})";
        }

        $this->params['bind'] = array_merge($this->params['bind'] ?? [], $bind);

        return $this;
    }

    /**
     * Paginate the query into a length-aware paginator.
     *
     * Runs a COUNT over the current conditions (ignoring any limit/order) to
     * resolve the total, then fetches a single page of results. When $page is
     * null it is resolved from the current request.
     *
     * Usage: $builder->paginate(15) or $builder->paginate(15, 2)
     */
    public function paginate(int $perPage = 15, ?int $page = null): LengthAwarePaginator
    {
        $builder = $this->applyScopes();
        $page = $page ?? Paginator::resolveCurrentPage();
        $page = max($page, 1);

        $total = $builder->getCountForPagination();

        $items = $total > 0
            ? $builder->forPageItems($perPage, $page)
            : new Collection();

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    /**
     * Paginate without a total count (Laravel parity "simple" pagination).
     *
     * Fetches one extra row beyond $perPage to detect whether a next page
     * exists; the paginator trims the extra row and exposes hasMorePages().
     *
     * Usage: $builder->simplePaginate(15) or $builder->simplePaginate(15, 2)
     */
    public function simplePaginate(int $perPage = 15, ?int $page = null): Paginator
    {
        $builder = $this->applyScopes();
        $page = $page ?? Paginator::resolveCurrentPage();
        $page = max($page, 1);

        // Fetch perPage + 1 so the paginator can detect a following page.
        $items = $builder->forPageItems($perPage + 1, $page, ($page - 1) * $perPage);

        return new Paginator($items, $perPage, $page);
    }

    /**
     * Fetch a single page of results without mutating this builder's params.
     */
    private function forPageItems(int $perPage, int $page, ?int $offset = null): Collection
    {
        $offset = $offset ?? ($page - 1) * $perPage;

        $params = $this->getParams();
        $params['limit'] = $offset === 0 ? $perPage : [
            'number' => $perPage,
            'offset' => $offset,
        ];

        $modelName = $this->getModelName();

        $results = is_string($modelName) && method_exists($modelName, 'rawFind')
            ? $modelName::rawFind($params)
            : (clone $this)->setPageParams($params)->execute();

        if ($this->eagerLoad !== []) {
            return $this->eagerLoadRelations($results);
        }

        return new Collection(iterator_to_array($results, false));
    }

    /**
     * Apply a prepared params array to this builder (used by pagination).
     *
     * @param array<string, mixed> $params
     */
    private function setPageParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Resolve the total row count for the current conditions, ignoring any
     * limit/order/columns previously applied.
     */
    private function getCountForPagination(): int
    {
        $params = $this->getParams();

        $countParams = [];

        if (!empty($params['conditions'])) {
            $countParams['conditions'] = $params['conditions'];
        }

        if (!empty($params['bind'])) {
            $countParams['bind'] = $params['bind'];
        }

        if (!empty($params['group'])) {
            $countParams['group'] = $params['group'];
        }

        $modelName = $this->getModelName();

        if (is_string($modelName) && method_exists($modelName, 'count')) {
            $count = $modelName::count($countParams === [] ? null : $countParams);

            // A grouped count returns a resultset of per-group counts.
            if (is_object($count) && $count instanceof \Countable) {
                return count($count);
            }

            return (int)$count;
        }

        return 0;
    }

    /**
     * Specify the columns to retrieve.
     * Usage: $builder->columns(['name', 'email'])
     *
     * @param mixed $columns
     */
    public function columns($columns): BuilderInterface
    {
        $this->params['columns'] = is_array($columns) ? implode(',', $columns) : $columns;

        return $this;
    }

    /**
     * Set the columns to retrieve (Laravel parity alias for columns()).
     *
     * Accepts an array or a variadic list of column names; defaults to `*`.
     *
     * @param array<int, string>|string $columns
     */
    public function select($columns = ['*']): BuilderInterface
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->columns($columns);
    }

    /**
     * Append columns to an existing select (Laravel parity).
     *
     * @param array<int, string>|string $column
     */
    public function addSelect($column): BuilderInterface
    {
        $columns = is_array($column) ? $column : func_get_args();

        $existing = $this->params['columns'] ?? '';
        $existing = is_string($existing) && $existing !== '' ? explode(',', $existing) : [];

        return $this->columns(array_merge($existing, $columns));
    }

    /**
     * Order results by a column.
     * Usage: $builder->orderBy('created_at', 'desc')
     *
     * @param string $column
     */
    public function orderBy($column, ?string $direction = null): BuilderInterface
    {
        if ($direction !== null && is_string($column)) {
            $column = [$column . ' ' . $direction];
        } elseif (is_string($column)) {
            $column = [$column];
        }

        $this->params['order'] = implode(',', $column);

        return $this;
    }

    /**
     * Order results by the given column, newest first (Laravel parity).
     */
    public function latest(string $column = 'created_at'): BuilderInterface
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order results by the given column, oldest first (Laravel parity).
     */
    public function oldest(string $column = 'created_at'): BuilderInterface
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Conditionally apply query modifications (Laravel parity).
     *
     * When $value is truthy, $callback receives the builder and the resolved
     * value. Otherwise $default (if given) is applied. A Closure $value is
     * resolved against the builder first.
     *
     * @param mixed $value
     */
    public function when($value, ?callable $callback = null, ?callable $default = null): BuilderInterface
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            if ($callback !== null) {
                $callback($this, $value);
            }
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Inverse of {@see when()} — apply $callback when $value is falsy.
     *
     * @param mixed $value
     */
    public function unless($value, ?callable $callback = null, ?callable $default = null): BuilderInterface
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if (!$value) {
            if ($callback !== null) {
                $callback($this, $value);
            }
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Pass the builder to the given callback and return it (Laravel parity).
     */
    public function tap(callable $callback): BuilderInterface
    {
        $callback($this);

        return $this;
    }

    /**
     * Order results by the given column descending (Laravel parity).
     */
    public function orderByDesc(string $column): BuilderInterface
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Drop the current ordering, optionally replacing it (Laravel parity).
     */
    public function reorder(?string $column = null, string $direction = 'asc'): BuilderInterface
    {
        unset($this->params['order']);

        if ($column !== null) {
            $this->orderBy($column, $direction);
        }

        return $this;
    }

    /**
     * Constrain the query to a single page of results (Laravel parity).
     */
    public function forPage(int $page, int $perPage = 15): BuilderInterface
    {
        return $this->limit($perPage, ($page - 1) * $perPage);
    }

    /**
     * Add a condition comparing two columns (Laravel parity).
     *
     * Two-argument form defaults the operator to `=`. Column names are
     * emitted verbatim — no bind parameter is registered.
     */
    public function whereColumn(string $first, ?string $operator = null, ?string $second = null): BuilderInterface
    {
        [$operator, $second] = $this->normalizeColumnComparison($operator, $second);

        return $this->whereRaw("{$first} {$operator} {$second}");
    }

    /**
     * OR variant of {@see whereColumn()}.
     */
    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): BuilderInterface
    {
        [$operator, $second] = $this->normalizeColumnComparison($operator, $second);

        return $this->orWhereRaw("{$first} {$operator} {$second}");
    }

    /**
     * Resolve the (operator, second) pair for a column comparison, defaulting
     * the operator to `=` when only a second column is supplied.
     *
     * @return array{0: string, 1: string}
     */
    private function normalizeColumnComparison(?string $operator, ?string $second): array
    {
        if ($second === null) {
            return ['=', (string)$operator];
        }

        return [(string)$operator, $second];
    }

    /**
     * Set a raw ordering expression (Laravel parity).
     */
    public function orderByRaw(string $sql): BuilderInterface
    {
        $this->params['order'] = $sql;

        return $this;
    }

    /**
     * Specify the maximum number of results and offset.
     * Usage: $builder->limit(10, 30) // fetch 10 items starting at offset 30
     *
     * @param int $limit
     * @param int $offset
     *
     * @throws \InvalidArgumentException When a negative offset is supplied.
     */
    public function limit($limit, $offset = 0): BuilderInterface
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException(sprintf('Offset must not be negative, [%d] given.', $offset));
        }

        $this->params['limit'] = $offset === 0 ? $limit : [
            'number' => $limit,
            'offset' => $offset,
        ];

        return $this;
    }

    /**
     * Set group by conditions.
     * Usage: $builder->groupBy('account_id')
     *
     * @param string $group
     */
    public function groupBy($group): BuilderInterface
    {
        $this->params['group'] = $group;

        return $this;
    }

    public function withTrashed(bool $withTrashed = true): BuilderInterface
    {
        return $this->invokeMacro(__FUNCTION__, [$withTrashed]) ?? $this;
    }

    public function onlyTrashed(): BuilderInterface
    {
        return $this->invokeMacro(__FUNCTION__) ?? $this;
    }

    public function withoutTrashed(): BuilderInterface
    {
        return $this->invokeMacro(__FUNCTION__) ?? $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        $macro = $this->invokeMacro($method, $arguments);
        if ($macro !== null) {
            return $macro;
        }

        $model = $this->getEloquentModel();
        $scope = 'scope' . ucfirst($method);

        if ($model !== null && method_exists($model, $scope)) {
            return $model->{$scope}($this, ...$arguments) ?? $this;
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method [%s] on builder [%s].', $method, static::class));
    }

    protected function invokeMacro(string $method, array $arguments = []): mixed
    {
        if (!isset($this->macros[$method])) {
            return null;
        }

        return ($this->macros[$method])($this, ...$arguments);
    }

    protected function resolveScopeIdentifier($scope): string
    {
        if ($scope instanceof Scope) {
            return get_class($scope);
        }

        if ($scope instanceof Closure) {
            return spl_object_hash($scope);
        }

        if (is_string($scope)) {
            return $scope;
        }

        throw new \InvalidArgumentException('Unable to resolve scope identifier.');
    }
}
