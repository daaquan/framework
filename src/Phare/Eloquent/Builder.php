<?php

namespace Phare\Eloquent;

use Closure;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\ModelInterface;
use Phare\Collections\Collection;
use Phare\Eloquent\Relations\Relation;

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
            $builder = new self();
            $field($builder);

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

    public function update(array $attributes): int
    {
        $models = iterator_to_array($this->applyScopes()->get(), false);

        if ($models === []) {
            return 0;
        }

        /** @var Model $model */
        $model = $models[0];
        $keyName = $model->getKeyName();
        $ids = array_values(array_filter(
            array_map(static fn (Model $item) => $item->readAttribute($keyName), $models),
            static fn ($id) => $id !== null
        ));

        if ($ids === []) {
            return 0;
        }

        $columns = array_keys($attributes);
        $assignments = implode(', ', array_map(static fn (string $column) => $column . ' = ?', $columns));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $model->getWriteConnection()->execute(
            sprintf(
                'UPDATE %s SET %s WHERE %s IN (%s)',
                $model->getTable(),
                $assignments,
                $keyName,
                $placeholders
            ),
            array_merge(array_values($attributes), $ids)
        );

        return count($ids);
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
        $this->params['conditions'] = "({$this->params['conditions']}) OR ($conditions)";
        $this->params['bind'] = array_merge($this->params['bind'] ?? [], $bind);

        return $this;
    }

    /**
     * Set pagination parameters.
     * Usage: $builder->paginate(2, 15) // page 2, 15 items per page
     *
     * @param int $page
     * @param int $limit
     */
    public function paginate($page, $limit): BuilderInterface
    {
        $this->params['limit'] = [
            'number' => $limit,
            'offset' => ($page - 1) * $limit,
        ];

        return $this;
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
     * Specify the maximum number of results and offset.
     * Usage: $builder->limit(10, 30) // fetch 10 items starting at offset 30
     *
     * @param int $limit
     * @param int $offset
     */
    public function limit($limit, $offset = 0): BuilderInterface
    {
        $this->params['limit'] = $offset === 0 ? $limit : [
            'number' => $limit,
            'offset' => abs($offset),
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
