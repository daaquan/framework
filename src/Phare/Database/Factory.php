<?php

namespace Phare\Database;

use Phare\Contracts\Foundation\Application;
use Phalcon\Db\Adapter\Pdo\AbstractPdo;

class Factory
{
    protected Application $app;
    protected AbstractPdo $db;
    protected string $model;
    protected int $count = 1;
    protected array $states = [];
    protected array $afterMaking = [];
    protected array $afterCreating = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app->make('db');
    }

    public function for(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function count(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    public function state(array $attributes): self
    {
        $this->states = array_merge($this->states, $attributes);
        return $this;
    }

    public function afterMaking(\Closure $callback): self
    {
        $this->afterMaking[] = $callback;
        return $this;
    }

    public function afterCreating(\Closure $callback): self
    {
        $this->afterCreating[] = $callback;
        return $this;
    }

    public function make(array $attributes = []): array
    {
        $instances = [];

        for ($i = 0; $i < $this->count; $i++) {
            $instance = $this->makeInstance($attributes);
            
            foreach ($this->afterMaking as $callback) {
                $callback($instance);
            }
            
            $instances[] = $instance;
        }

        return $this->count === 1 ? $instances[0] : $instances;
    }

    public function create(array $attributes = []): array
    {
        $instances = $this->make($attributes);
        $instances = is_array($instances) ? $instances : [$instances];

        foreach ($instances as $instance) {
            $this->saveInstance($instance);
            
            foreach ($this->afterCreating as $callback) {
                $callback($instance);
            }
        }

        return $this->count === 1 ? $instances[0] : $instances;
    }

    protected function makeInstance(array $attributes = []): array
    {
        $definition = $this->getDefinition();
        $data = array_merge($definition, $this->states, $attributes);
        
        return $data;
    }

    protected function saveInstance(array $instance): void
    {
        $table = $this->getTableName();
        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($instance)));
        $placeholders = implode(', ', array_fill(0, count($instance), '?'));
        
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->db->execute($sql, array_values($instance));
    }

    protected function getDefinition(): array
    {
        $factoryClass = $this->getFactoryClass();
        
        if (!class_exists($factoryClass)) {
            throw new \RuntimeException("Factory class {$factoryClass} not found.");
        }
        
        $factory = new $factoryClass();
        return $factory->definition();
    }

    protected function getFactoryClass(): string
    {
        $modelName = class_basename($this->model);
        return "Database\\Factories\\{$modelName}Factory";
    }

    protected function getTableName(): string
    {
        // Convert model class name to table name (e.g., User -> users)
        $modelName = class_basename($this->model);
        return strtolower($modelName) . 's';
    }
}

abstract class BaseFactory
{
    abstract public function definition(): array;

    protected function faker(): \Faker\Generator
    {
        return \Faker\Factory::create();
    }
}