<?php

namespace Phare\Eloquent;

use Phalcon\Di\DiInterface;
use Phare\Collections\Collection;
use Phare\Pagination\LengthAwarePaginator;

/**
 * This used to extend Phalcon\Mvc\Model\CriteriaInterface, which forced every
 * Phare query builder to carry Phalcon's whole criteria surface. The few
 * members that were actually used are declared here instead.
 */
interface BuilderInterface
{
    public function setModelName(string $modelName): static;

    public function getModelName(): ?string;

    /** @return array<string, mixed> */
    public function getParams(): array;

    public function setDI(DiInterface $container): void;

    public function getDI(): ?DiInterface;

    /** @return Collection<int, Model> */
    public function get(): Collection;

    public function first(): ?Model;

    public function last(): ?Model;

    public function columns($columns): BuilderInterface;

    public function limit(int $limit, int $offset = 0): BuilderInterface;

    public function with($relations, $callback = null): BuilderInterface;

    public function withoutGlobalScope($scope): BuilderInterface;

    public function withoutGlobalScopes($scopes = null): BuilderInterface;

    public function withTrashed(bool $withTrashed = true): BuilderInterface;

    public function onlyTrashed(): BuilderInterface;

    public function withoutTrashed(): BuilderInterface;

    public function update(array $attributes): int;

    public function delete(): int;

    public function where($field, $operator = null, $value = null): BuilderInterface;

    public function orWhere($field, $operator = null, $value = null): BuilderInterface;

    public function whereIn($field, array $values): BuilderInterface;

    public function whereNotIn($field, array $values): BuilderInterface;

    public function whereBetween($field, $min, $max): BuilderInterface;

    public function whereNotBetween($field, $min, $max): BuilderInterface;

    public function whereNull($field): BuilderInterface;

    public function whereNotNull($field): BuilderInterface;

    public function whereLike($field, $value): BuilderInterface;

    public function whereNotLike($field, $value): BuilderInterface;

    public function whereRaw($conditions, array $bind = []): BuilderInterface;

    public function orWhereRaw($conditions, array $bind = []): BuilderInterface;

    public function orderBy($field, ?string $direction = null): BuilderInterface;

    public function latest(string $column = 'created_at'): BuilderInterface;

    public function oldest(string $column = 'created_at'): BuilderInterface;

    public function when($value, ?callable $callback = null, ?callable $default = null): BuilderInterface;

    public function unless($value, ?callable $callback = null, ?callable $default = null): BuilderInterface;

    public function tap(callable $callback): BuilderInterface;

    public function orderByDesc(string $column): BuilderInterface;

    public function reorder(?string $column = null, string $direction = 'asc'): BuilderInterface;

    public function forPage(int $page, int $perPage = 15): BuilderInterface;

    public function whereColumn(string $first, ?string $operator = null, ?string $second = null): BuilderInterface;

    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): BuilderInterface;

    public function orderByRaw(string $sql): BuilderInterface;

    public function select($columns = ['*']): BuilderInterface;

    public function addSelect($column): BuilderInterface;

    public function groupBy($field): BuilderInterface;

    public function paginate(int $perPage = 15, ?int $page = null): LengthAwarePaginator;
}
