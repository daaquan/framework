<?php

use Phare\Console\Commands\MigrateCommand;
use Phare\Database\Schema\Blueprint;
use Tests\TestCase;

class MigrateCommandTest extends TestCase
{
    protected string $testMigrationPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary directory for test migrations
        $this->testMigrationPath = sys_get_temp_dir() . '/test_migrate_command';
        if (!is_dir($this->testMigrationPath)) {
            mkdir($this->testMigrationPath, 0755, true);
        }
        
        // Clean up any existing test tables
        $connection = $this->app->make('db');
        $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
        $testTables = ['test_command_table', 'migrations'];
        foreach ($testTables as $table) {
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up test migrations directory
        if (is_dir($this->testMigrationPath)) {
            $files = glob($this->testMigrationPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testMigrationPath);
        }
        
        // Clean up test tables
        $connection = $this->app->make('db');
        $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
        $testTables = ['test_command_table', 'migrations'];
        foreach ($testTables as $table) {
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        }
        
        parent::tearDown();
    }

    protected function createTestMigration(string $name, string $content): string
    {
        $filename = date('Y_m_d_His') . '_' . $name . '.php';
        $filepath = $this->testMigrationPath . '/' . $filename;
        file_put_contents($filepath, $content);
        return $filepath;
    }
}

it('can run migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_command_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_command_table');
    }
};
PHP;

    $this->createTestMigration('create_test_command_table', $migrationContent);
    
    $command = new MigrateCommand();
    $command->setApplication($this->app);
    
    // Mock the command options
    $command = new class extends MigrateCommand {
        protected array $options = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        public function line(string $message): void
        {
            // Mock output
        }
    };
    
    $command->setApplication($this->app);
    $result = $command->handle();
    
    expect($result)->toBe(0);
    
    // Verify table was created
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_command_table'))->toBe(true);
})->uses(MigrateCommandTest::class);

it('can rollback migrations', function () {
    // First create and run a migration
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_command_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_command_table');
    }
};
PHP;

    $this->createTestMigration('create_rollback_table', $migrationContent);
    
    $command = new class extends MigrateCommand {
        protected array $options = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        public function line(string $message): void
        {
            // Mock output
        }
    };
    
    $command->setApplication($this->app);
    
    // Run migration first
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table exists
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_command_table'))->toBe(true);
    
    // Now rollback
    $command->setOption('rollback', '1');
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table was dropped
    expect($schema->hasTable('test_command_table'))->toBe(false);
})->uses(MigrateCommandTest::class);

it('can reset migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_command_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_command_table');
    }
};
PHP;

    $this->createTestMigration('create_reset_table', $migrationContent);
    
    $command = new class extends MigrateCommand {
        protected array $options = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        public function line(string $message): void
        {
            // Mock output
        }
    };
    
    $command->setApplication($this->app);
    
    // Run migration first
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table exists and migration is logged
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_command_table'))->toBe(true);
    
    $migrationCount = $connection->fetchOne("SELECT COUNT(*) as count FROM migrations");
    expect($migrationCount['count'])->toBe(1);
    
    // Now reset
    $command->setOption('reset', true);
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table was dropped and migration log cleared
    expect($schema->hasTable('test_command_table'))->toBe(false);
    
    $migrationCount = $connection->fetchOne("SELECT COUNT(*) as count FROM migrations");
    expect($migrationCount['count'])->toBe(0);
})->uses(MigrateCommandTest::class);

it('can refresh migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_command_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_command_table');
    }
};
PHP;

    $this->createTestMigration('create_refresh_table', $migrationContent);
    
    $command = new class extends MigrateCommand {
        protected array $options = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        public function line(string $message): void
        {
            // Mock output
        }
    };
    
    $command->setApplication($this->app);
    
    // Run migration first
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table exists
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_command_table'))->toBe(true);
    
    // Now refresh
    $command->setOption('refresh', true);
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Verify table still exists (was reset and re-run)
    expect($schema->hasTable('test_command_table'))->toBe(true);
})->uses(MigrateCommandTest::class);

it('handles fresh option', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_command_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }
};
PHP;

    $this->createTestMigration('create_fresh_table', $migrationContent);
    
    $command = new class extends MigrateCommand {
        protected array $options = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        public function line(string $message): void
        {
            // Mock output
        }
        
        protected function getAllTables($connection): array
        {
            return ['test_command_table', 'some_other_table'];
        }
    };
    
    $command->setApplication($this->app);
    $command->setOption('fresh', true);
    
    $result = $command->handle();
    expect($result)->toBe(0);
    
    // Fresh should drop all tables and re-run migrations
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_command_table'))->toBe(true);
})->uses(MigrateCommandTest::class);

it('reports when nothing to migrate', function () {
    $command = new class extends MigrateCommand {
        protected array $options = [];
        protected array $output = [];
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        protected function getMigrationPaths(): array
        {
            return [sys_get_temp_dir() . '/test_migrate_command'];
        }
        
        public function info(string $message): void
        {
            $this->output[] = $message;
        }
        
        public function line(string $message): void
        {
            $this->output[] = $message;
        }
        
        public function getOutput(): array
        {
            return $this->output;
        }
    };
    
    $command->setApplication($this->app);
    $result = $command->handle();
    
    expect($result)->toBe(0);
    expect($command->getOutput())->toContain('Nothing to migrate.');
})->uses(MigrateCommandTest::class);