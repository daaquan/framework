<?php

namespace Phare\Database;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum;

/**
 * Phare-typed seam over Phalcon's PDO adapter.
 *
 * Everything in Phare that needs a database should depend on this, not on
 * `Phalcon\Db\Adapter\Pdo\AbstractPdo` — that inherited type dragged Phalcon's
 * whole adapter surface into Phare's public API. Use `getAdapter()` only where
 * a Phalcon-native object is genuinely required (Phalcon's own ORM internals).
 */
class Connection
{
    protected AbstractPdo $adapter;

    public function __construct(AbstractPdo $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Normalize either form into a Connection, so call sites can keep accepting
     * a raw adapter for backwards compatibility.
     */
    public static function wrap(self|AbstractPdo $connection): self
    {
        return $connection instanceof self ? $connection : new self($connection);
    }

    /** Lower-cased driver name: mysql, sqlite, pgsql. */
    public function getDriverName(): string
    {
        return strtolower($this->adapter->getType());
    }

    /** @return array<int, array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->adapter->fetchAll($sql, Enum::FETCH_ASSOC, $bindings);
    }

    /** @return array<string, mixed>|null */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->adapter->fetchOne($sql, Enum::FETCH_ASSOC, $bindings);

        // Phalcon returns an empty array, not null, when nothing matches.
        return empty($row) ? null : $row;
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->adapter->execute($sql, $bindings);
    }

    public function lastInsertId(?string $sequence = null): int|string|false
    {
        return $this->adapter->lastInsertId($sequence);
    }

    public function beginTransaction(): bool
    {
        return $this->adapter->begin();
    }

    public function commit(): bool
    {
        return $this->adapter->commit();
    }

    public function rollBack(): bool
    {
        return $this->adapter->rollback();
    }

    /** Escape hatch for the Phalcon-native code paths that are not decoupled yet. */
    public function getAdapter(): AbstractPdo
    {
        return $this->adapter;
    }
}
