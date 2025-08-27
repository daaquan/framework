<?php

namespace Phare\Database\MySql;

use Phalcon\Db\Adapter\Pdo\AbstractPdo as PDO;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Db\Adapter\Pdo\Postgresql as PdoPostgresql;
use Phalcon\Db\Adapter\Pdo\Sqlite as PdoSqlite;
use Phare\Contracts\Foundation\Application;

class DatabaseManager
{
    use HandlesTransactions;

    public function __construct(protected Application $app, protected array $databases)
    {
    }

    public function getConnectionService(string $serviceName): string
    {
        if (!$this->hasConnectionService($serviceName)) {
            throw new \RuntimeException("Database service `$serviceName` is not defined.");
        }

        return $serviceName;
    }

    public function hasConnectionService(string $serviceName): bool
    {
        return isset($this->databases[$serviceName]);
    }

    public function setupDatabases($type = null)
    {
        $manager = $this;
        foreach ($this->databases as $name => $config) {
            if (!isset($config['driver']) || ($type !== null && $config['driver'] !== $type)) {
                continue;
            }
            $this->app->singleton($name, function () use ($config, $manager) {
                return $manager->getConnection($config);
            });
        }

        return $this;
    }

    public function getConnection(array $dbConfig, ?string $type = null): PDO
    {
        if ($dbConfig['driver'] === 'sqlite') {
            return new PdoSqlite(['dbname' => $this->app->databasePath("{$dbConfig['database']}.sqlite")]);
        }

        $host = $this->selectHost($dbConfig, $type);
        $options = $this->getPdoOptions($host, $dbConfig);

        return $this->createPdoInstance($dbConfig['driver'], $options);
    }

    private function selectHost(array $dbConfig, ?string $type): string
    {
        if (isset($dbConfig['read'], $dbConfig['write'])) {
            if ($type === 'read' || $type === 'write') {
                return $dbConfig[$type];
            }

            return $this->isWriteOperation() ? $dbConfig['write'] : $dbConfig['read'];
        }

        return $dbConfig['host'];
    }

    private function getPdoOptions(string $host, array $dbConfig): array
    {
        return [
            'host' => $host,
            'port' => $dbConfig['port'],
            'dbname' => $dbConfig['database'],
            'username' => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'options' => $dbConfig['driver'] === 'mysql' ? $this->getOptions() : [],
        ];
    }

    private function createPdoInstance(string $driver, array $options): PDO
    {
        return match ($driver) {
            'pgsql' => new PdoPostgresql($options),
            'mysql' => new PdoMysql($options),
            default => throw new \RuntimeException("Driver `{$driver}` is not supported."),
        };
    }

    private function getOptions(): array
    {
        return [
            \PDO::ATTR_AUTOCOMMIT => true, // Use manual commit when handling transactions
            \PDO::ATTR_PERSISTENT => true, // Persistent connection for better performance
            \PDO::ATTR_STRINGIFY_FETCHES => false, // Do not convert values to strings
            \PDO::ATTR_EMULATE_PREPARES => false, // Use prepared statements (SQL injection protection)
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true, // Enable LOAD DATA LOCAL INFILE
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        ];
    }

    protected function isWriteOperation(): bool
    {
        // Check if we're in a transaction (writes typically need to go to the write server)
        if ($this->inTransaction()) {
            return true;
        }

        // Default to read operations unless explicitly told otherwise
        // This can be overridden by specific implementations or middleware
        return false;
    }
}
