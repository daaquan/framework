<?php

namespace Phare\Eloquent;

use Phalcon\Mvc\Model\CriteriaInterface;
use Phalcon\Mvc\Model\ResultsetInterface;

interface BuilderInterface extends CriteriaInterface
{
    public function get(): ResultsetInterface;

    public function first(): ?\Phalcon\Mvc\ModelInterface;

    public function last(): ?\Phalcon\Mvc\ModelInterface;

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
