<?php

namespace Phare\Eloquent\Query;

use Phalcon\Db\Exception;
use Phare\Collections\Collection;
use Phare\Database\Connection;
use Phare\Eloquent\Exceptions\QueryException;
use Phare\Eloquent\Model;

/**
 * Runs a compiled query and hydrates the rows into models.
 *
 * This is the replacement for routing Builder's query parameters through
 * Phalcon\Mvc\Model::find(), which is what tied Phare's Model to Phalcon's
 * active record.
 */
class Executor
{
    protected Compiler $compiler;

    public function __construct(protected Connection $connection, ?Compiler $compiler = null)
    {
        $this->compiler = $compiler ?? new Compiler();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param array<string, mixed> $params
     * @return Collection<int, Model>
     */
    public function select(Model $model, array $params): Collection
    {
        [$sql, $bindings] = $this->compiler->compileSelect($model->getTable(), $params);

        $rows = $this->run($sql, $bindings, fn (): array => $this->connection->select($sql, $bindings));

        return new Collection(array_map(fn (array $row): Model => $this->hydrate($model, $row), $rows));
    }

    /** @param  array<string, mixed>  $params */
    public function count(Model $model, array $params): int
    {
        [$sql, $bindings] = $this->compiler->compileCount($model->getTable(), $params);

        $row = $this->run($sql, $bindings, fn (): ?array => $this->connection->selectOne($sql, $bindings));

        return (int)($row['aggregate'] ?? 0);
    }

    /**
     * Driver failures become a Phare exception carrying the SQL that failed.
     *
     * @param array<string, mixed> $bindings
     */
    protected function run(string $sql, array $bindings, \Closure $query): mixed
    {
        try {
            return $query();
        } catch (\PDOException|Exception $e) {
            throw new QueryException($sql, $bindings, $e);
        }
    }

    /** @param  array<string, mixed>  $row */
    protected function hydrate(Model $model, array $row): Model
    {
        $class = $model::class;

        return (new $class())->hydrate($row);
    }
}
