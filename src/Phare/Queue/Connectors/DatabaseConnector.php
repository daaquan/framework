<?php

namespace Phare\Queue\Connectors;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Database\MySql\DatabaseManager;
use Phare\Queue\DatabaseQueue;
use Phare\Queue\QueueInterface;

class DatabaseConnector implements ConnectorInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Establish a queue connection backed by a real database adapter when one
     * can be resolved from the container; otherwise the queue falls back to
     * array/test mode (no silent fake persistence).
     */
    public function connect(array $config): QueueInterface
    {
        $config = array_merge($this->config, $config);

        return new DatabaseQueue($config, $this->resolveConnection($config));
    }

    /**
     * Resolve a Phalcon PDO adapter for the queue's database connection.
     */
    protected function resolveConnection(array $config): ?AbstractPdo
    {
        if (isset($config['_connection']) && $config['_connection'] instanceof AbstractPdo) {
            return $config['_connection'];
        }

        $app = app();
        if ($app === null) {
            return null;
        }

        $name = $config['connection'] ?? null;

        // Prefer the database manager so a named connection can be honoured.
        if ($app->bound(DatabaseManager::class)) {
            try {
                /** @var DatabaseManager $manager */
                $manager = $app->make(DatabaseManager::class);

                return $manager->connection(is_string($name) ? $name : null);
            } catch (\Throwable) {
                // Fall through to the plain `db` binding below.
            }
        }

        if ($app->bound('db')) {
            try {
                $db = $app->make('db');

                return $db instanceof AbstractPdo ? $db : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
