<?php

namespace Phare\Eloquent\Exceptions;

/**
 * Thrown when a compiled query fails at the driver.
 *
 * Queries used to run through Phalcon's PHQL engine, so failures surfaced as
 * Phalcon\Mvc\Model\Exception. Phare owns query execution now, so it owns the
 * failure type too.
 *
 * The SQL is included because it is what makes the error diagnosable. The
 * binding values are deliberately not: they are user data and can hold
 * secrets. Only the binding names are listed.
 */
class QueryException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $bindings
     */
    public function __construct(
        protected string $sql,
        array $bindings,
        \Throwable $previous
    ) {
        parent::__construct(
            sprintf(
                '%s (SQL: %s) (bindings: %s)',
                $previous->getMessage(),
                $sql,
                $bindings === [] ? 'none' : implode(', ', array_keys($bindings))
            ),
            0,
            $previous
        );
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
