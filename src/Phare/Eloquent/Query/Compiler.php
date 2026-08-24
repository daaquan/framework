<?php

namespace Phare\Eloquent\Query;

/**
 * Compiles the query-parameter array that Builder assembles into real SQL.
 *
 * Builder used to hand this array to Phalcon's PHQL engine via
 * Phalcon\Mvc\Model::find(). Compiling it here is what lets Model stop
 * extending Phalcon\Mvc\Model.
 *
 * Conditions arrive as SQL fragments carrying Phalcon's `:name:` placeholders;
 * they become PDO's `:name`.
 */
class Compiler
{
    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function compileSelect(string $table, array $params): array
    {
        return $this->compile('SELECT ' . $this->columns($params) . ' FROM ' . $table, $params, true);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function compileCount(string $table, array $params): array
    {
        return $this->compile('SELECT COUNT(*) AS aggregate FROM ' . $table, $params, false);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function compile(string $head, array $params, bool $ordered): array
    {
        $sql = [$head];
        $bindings = [];

        $conditions = $params['conditions'] ?? null;

        if (is_string($conditions) && trim($conditions) !== '') {
            [$conditions, $bindings] = $this->rewritePlaceholders($conditions, $params['bind'] ?? []);
            $sql[] = 'WHERE ' . $conditions;
        }

        if (($group = $this->commaList($params['group'] ?? null)) !== null) {
            $sql[] = 'GROUP BY ' . $group;
        }

        if ($ordered && ($order = $this->commaList($params['order'] ?? null)) !== null) {
            $sql[] = 'ORDER BY ' . $order;
        }

        if (($limit = $this->limit($params['limit'] ?? null)) !== null) {
            $sql[] = $limit;
        }

        return [implode(' ', $sql), $bindings];
    }

    /** @param  array<string, mixed>  $params */
    protected function columns(array $params): string
    {
        $columns = $params['columns'] ?? null;

        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        if (!is_string($columns) || trim($columns) === '' || trim($columns) === '*') {
            return '*';
        }

        return $columns;
    }

    /**
     * Phalcon writes `:name:`; PDO wants `:name`. Only the bindings the
     * conditions actually reference are returned — PDO errors on extras.
     *
     * @param array<string, mixed> $bind
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function rewritePlaceholders(string $conditions, array $bind): array
    {
        $used = [];

        $conditions = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*):/',
            function (array $match) use ($bind, &$used): string {
                if (array_key_exists($match[1], $bind)) {
                    $used[$match[1]] = $bind[$match[1]];
                }

                return ':' . $match[1];
            },
            $conditions
        ) ?? $conditions;

        return [$conditions, $used];
    }

    protected function commaList(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /** Limits are interpolated, never bound, so they must be proven numeric. */
    protected function limit(mixed $limit): ?string
    {
        if ($limit === null || $limit === '' || $limit === []) {
            return null;
        }

        if (is_array($limit)) {
            $number = $this->integer($limit['number'] ?? null, 'limit');
            $offset = $this->integer($limit['offset'] ?? 0, 'offset');

            if ($number === null) {
                return null;
            }

            return $offset > 0 ? "LIMIT {$number} OFFSET {$offset}" : "LIMIT {$number}";
        }

        $number = $this->integer($limit, 'limit');

        return $number === null ? null : "LIMIT {$number}";
    }

    protected function integer(mixed $value, string $what): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException(
                sprintf('Query %s must be an integer, got %s.', $what, get_debug_type($value))
            );
        }

        return (int)$value;
    }
}
