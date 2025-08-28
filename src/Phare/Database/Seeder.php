<?php

namespace Phare\Database;

use Phare\Contracts\Foundation\Application;
use Phalcon\Db\Adapter\Pdo\AbstractPdo;

abstract class Seeder
{
    protected Application $app;
    protected AbstractPdo $db;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app->make('db');
    }

    abstract public function run(): void;

    protected function call(string|array $seeders): void
    {
        $seeders = is_array($seeders) ? $seeders : [$seeders];
        
        foreach ($seeders as $seeder) {
            if (is_string($seeder)) {
                $seeder = new $seeder($this->app);
            }
            
            $seeder->run();
        }
    }

    protected function create(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }

        // Handle single record or multiple records
        $isMultiple = isset($data[0]) && is_array($data[0]);
        $records = $isMultiple ? $data : [$data];

        foreach ($records as $record) {
            $columns = implode(', ', array_map([$this, 'wrapColumn'], array_keys($record)));
            $placeholders = implode(', ', array_fill(0, count($record), '?'));
            
            $sql = "INSERT INTO {$this->wrapTable($table)} ({$columns}) VALUES ({$placeholders})";
            $this->db->execute($sql, array_values($record));
        }
    }

    protected function table(string $table): SeederTable
    {
        return new SeederTable($this->db, $table);
    }

    protected function wrapTable(string $table): string
    {
        return "`{$table}`";
    }

    protected function wrapColumn(string $column): string
    {
        return "`{$column}`";
    }

    protected function factory(string $model, int $count = 1): Factory
    {
        return $this->app->make(Factory::class)->for($model)->count($count);
    }
}

class SeederTable
{
    protected AbstractPdo $db;
    protected string $table;

    public function __construct(AbstractPdo $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function insert(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $isMultiple = isset($data[0]) && is_array($data[0]);
        $records = $isMultiple ? $data : [$data];

        foreach ($records as $record) {
            $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($record)));
            $placeholders = implode(', ', array_fill(0, count($record), '?'));
            
            $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
            $this->db->execute($sql, array_values($record));
        }
    }

    public function truncate(): void
    {
        $this->db->execute("TRUNCATE TABLE `{$this->table}`");
    }

    public function delete(): void
    {
        $this->db->execute("DELETE FROM `{$this->table}`");
    }
}