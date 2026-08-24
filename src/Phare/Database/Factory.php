<?php

namespace Phare\Database;

use Phare\Collections\Str;
use Phare\Contracts\Foundation\Application;
use Phare\Eloquent\Model;

class Factory
{
    protected Application $app;

    protected Connection $db;

    protected string $model;

    protected int $count = 1;

    protected array $states = [];

    protected array $afterMaking = [];

    protected array $afterCreating = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = Connection::wrap($app->make('db'));
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
        if ($this->count === 1) {
            $instance = $this->make($attributes);
            $this->saveInstance($instance);

            foreach ($this->afterCreating as $callback) {
                $callback($instance);
            }

            return $instance;
        }

        $instances = $this->make($attributes);

        foreach ($instances as $instance) {
            $this->saveInstance($instance);

            foreach ($this->afterCreating as $callback) {
                $callback($instance);
            }
        }

        return $instances;
    }

    protected function makeInstance(array $attributes = []): array
    {
        $definition = $this->getDefinition();
        $data = array_merge($definition, $this->states, $attributes);

        return $data;
    }

    protected function saveInstance(array $instance): void
    {
        // Prefer persisting through a model instance so the model's configured
        // connection is used and casts/mutators/timestamps are applied.
        $model = $this->newModel();

        if ($model !== null) {
            foreach ($instance as $key => $value) {
                $model->setAttribute((string)$key, $value);
            }

            $model->create();

            return;
        }

        // Fall back to a raw insert when the target isn't an Eloquent model.
        $table = $this->getTableName();
        $columns = implode(', ', array_map(fn ($col) => "`{$col}`", array_keys($instance)));
        $placeholders = implode(', ', array_fill(0, count($instance), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->db->statement($sql, array_values($instance));
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
        // Defer to the model itself so the table name matches exactly what the
        // ORM resolves (custom $table overrides, correct pluralisation, etc.).
        $model = $this->newModel();

        if ($model !== null) {
            return $model->getTable();
        }

        // Fall back to the same pluralisation the Model uses when no instance
        // can be built (e.g. Category -> categories, Person -> people).
        return Str::tableize(class_basename($this->model));
    }

    protected function newModel(): ?Model
    {
        if (!is_string($this->model) || !is_subclass_of($this->model, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $this->model();

        return $model;
    }
}
