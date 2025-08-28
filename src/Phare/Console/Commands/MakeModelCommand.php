<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeModelCommand extends Command
{
    protected string $signature = 'make:model {name : The name of the model} {--migration : Create a migration file} {--factory : Create a factory file}';

    protected string $description = 'Create a new Eloquent model class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $createMigration = $this->option('migration');
        $createFactory = $this->option('factory');

        $modelName = $this->getModelName($name);
        $path = $this->getModelPath($modelName);

        if ($this->files->exists($path)) {
            $this->error("Model [{$modelName}] already exists.");

            return 1;
        }

        $stub = $this->getStub();
        $content = $this->buildClass($modelName, $stub);

        $this->makeDirectory($path);
        $this->files->put($path, $content);

        $relativePath = str_replace($this->app->basePath() . '/', '', $path);
        $this->info("Model created successfully at [{$relativePath}].");

        // Create migration if requested
        if ($createMigration) {
            $this->createMigration($modelName);
        }

        // Create factory if requested
        if ($createFactory) {
            $this->createFactory($modelName);
        }

        return 0;
    }

    protected function getModelName(string $name): string
    {
        return trim(str_replace('/', '\\', $name), '\\');
    }

    protected function getModelPath(string $name): string
    {
        $path = str_replace('\\', '/', $name) . '.php';

        return $this->app->basePath('app/Models/' . $path);
    }

    protected function buildClass(string $name, string $stub): string
    {
        $namespace = $this->getNamespace($name);
        $className = $this->getClassName($name);
        $tableName = $this->getTableName($className);

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ table }}' => $tableName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function getNamespace(string $name): string
    {
        $parts = explode('\\', $name);
        array_pop($parts); // Remove class name

        $namespace = 'App\\Models';
        if (!empty($parts)) {
            $namespace .= '\\' . implode('\\', $parts);
        }

        return $namespace;
    }

    protected function getClassName(string $name): string
    {
        return class_basename($name);
    }

    protected function getTableName(string $className): string
    {
        // Convert PascalCase to snake_case and pluralize
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Simple pluralization
        if (str_ends_with($tableName, 'y')) {
            return substr($tableName, 0, -1) . 'ies';
        } elseif (str_ends_with($tableName, ['s', 'sh', 'ch', 'x', 'z'])) {
            return $tableName . 'es';
        } else {
            return $tableName . 's';
        }
    }

    protected function createMigration(string $modelName): void
    {
        $className = $this->getClassName($modelName);
        $tableName = $this->getTableName($className);
        $migrationName = "create_{$tableName}_table";

        $this->call('make:migration', ['name' => $migrationName]);
    }

    protected function createFactory(string $modelName): void
    {
        $className = $this->getClassName($modelName);
        $factoryName = $className . 'Factory';

        $this->info("Factory [{$factoryName}] should be created manually in database/factories/");
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use Phare\Eloquent\Model;

class {{ class }} extends Model
{
    /**
     * The table associated with the model.
     */
    protected string $table = '{{ table }}';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        //
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        //
    ];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        //
    ];
}
STUB;
    }
}
