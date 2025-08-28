<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration {name : The migration name} {--create= : Create a new table} {--table= : Modify an existing table}';
    protected string $description = 'Create a new migration file';

    public function handle(): int
    {
        $name = $this->argument('name');
        $table = $this->option('table');
        $create = $this->option('create');
        
        $migrationName = $this->getMigrationName($name);
        $fileName = $this->getMigrationFileName($name);
        $path = $this->getApplication()->databasePath('migrations') . '/' . $fileName;
        
        if (file_exists($path)) {
            $this->error("Migration {$fileName} already exists!");
            return 1;
        }
        
        $this->ensureMigrationDirectory();
        
        $stub = $this->getStub($create, $table);
        $content = $this->populateStub($stub, $migrationName, $create ?: $table);
        
        file_put_contents($path, $content);
        
        $this->info("Migration {$fileName} created successfully.");
        
        return 0;
    }

    protected function getMigrationName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    protected function getMigrationFileName(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        return "{$timestamp}_{$name}.php";
    }

    protected function ensureMigrationDirectory(): void
    {
        $dir = $this->getApplication()->databasePath('migrations');
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function getStub(string $create = null, string $table = null): string
    {
        if ($create) {
            return $this->getCreateStub();
        }
        
        if ($table) {
            return $this->getUpdateStub();
        }
        
        return $this->getBlankStub();
    }

    protected function getCreateStub(): string
    {
        return <<<'STUB'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('{{table}}', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropIfExists('{{table}}');
    }
};
STUB;
    }

    protected function getUpdateStub(): string
    {
        return <<<'STUB'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('{{table}}', function (Blueprint $table) {
            //
        });
    }

    public function down(): void
    {
        $this->table('{{table}}', function (Blueprint $table) {
            //
        });
    }
};
STUB;
    }

    protected function getBlankStub(): string
    {
        return <<<'STUB'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
STUB;
    }

    protected function populateStub(string $stub, string $className, string $table = null): string
    {
        $replacements = [
            '{{class}}' => $className,
            '{{table}}' => $table ?: 'example_table',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }
}