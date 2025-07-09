<?php

namespace Phare\Eloquent;

use Phalcon\Mvc\Model\Criteria;
use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\ModelInterface;

/**
 * Eloquent Builder for Phalcon
 */
class Builder extends Criteria implements BuilderInterface
{
    /**
     * Get the first result of the query.
     */
    public function first(): ?ModelInterface
    {
        return $this->get()->getFirst();
    }

    /**
     * Get the last result of the query.
     */
    public function last(): ?ModelInterface
    {
        return $this->get()->getLast();
    }

    /**
     * Execute the query and return the result set.
     */
    public function get(): ResultsetInterface
    {
        return $this->execute();
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
        if ($field instanceof \Closure) {
            $builder = new self();
            $field($builder);

            $params = $builder->getParams();

            return [
                'conditions' => $params['conditions'],
                'bind' => $params['bind'],
            ];
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return [
            'conditions' => "$field $operator :$field:",
            'bind' => [$field => $value],
        ];
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
        $this->params['conditions'] = $conditions;
        $this->params['bind'] = $bind;

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
}
