<?php

namespace Phare\Eloquent;

use Phalcon\Mvc\Model\CriteriaInterface;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\ModelInterface;
use Phare\Collections\Collection;

interface BuilderInterface extends CriteriaInterface
{
    public function get(): ResultsetInterface|Collection;

    public function first(): ?ModelInterface;

    public function last(): ?ModelInterface;

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

    public function groupBy($field): BuilderInterface;

    public function paginate($page, $limit): BuilderInterface;
}
