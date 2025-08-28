<?php

use Phare\Database\Migrator;
use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;
use Tests\TestCase;

class MigratorTest extends TestCase
{
    protected Migrator $migrator;
    protected string $testMigrationPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $connection = $this->app->make('db');
        $this->migrator = new Migrator($this->app, $connection);
        
        // Create temporary directory for test migrations
        $this->testMigrationPath = sys_get_temp_dir() . '/test_migrations';
        if (!is_dir($this->testMigrationPath)) {
            mkdir($this->testMigrationPath, 0755, true);
        }
        
        // Clean up any existing test tables
        $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
        $testTables = ['test_migration_table', 'test_users_migration', 'migrations'];
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
        $testTables = ['test_migration_table', 'test_users_migration', 'migrations'];
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

it('creates migration table automatically', function () {
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    
    expect($schema->hasTable('migrations'))->toBe(true);
})->uses(MigratorTest::class);

it('can run single migration', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_migration_table');
    }
};
PHP;

    $migrationFile = $this->createTestMigration('create_test_migration_table', $migrationContent);
    
    $ran = $this->migrator->run([$this->testMigrationPath]);
    
    expect($ran)->toHaveCount(1);
    expect($ran[0])->toBe($migrationFile);
    
    // Verify table was created
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_migration_table'))->toBe(true);
    
    // Verify migration was logged
    $result = $connection->fetchOne("SELECT COUNT(*) as count FROM migrations");
    expect($result['count'])->toBe(1);
})->uses(MigratorTest::class);

it('can run multiple migrations in order', function () {
    $migration1Content = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_users_migration', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
};
PHP;

    $migration2Content = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('test_users_migration', function (Blueprint $table) {
            $table->string('email')->unique();
        });
    }
};
PHP;

    sleep(1); // Ensure different timestamps
    $file1 = $this->createTestMigration('create_users_table', $migration1Content);
    sleep(1); // Ensure different timestamps
    $file2 = $this->createTestMigration('add_email_to_users', $migration2Content);
    
    $ran = $this->migrator->run([$this->testMigrationPath]);
    
    expect($ran)->toHaveCount(2);
    
    // Verify both migrations ran
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_users_migration'))->toBe(true);
    expect($schema->hasColumn('test_users_migration', 'email'))->toBe(true);
})->uses(MigratorTest::class);

it('skips already run migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }
};
PHP;

    $this->createTestMigration('create_test_table', $migrationContent);
    
    // Run first time
    $ran1 = $this->migrator->run([$this->testMigrationPath]);
    expect($ran1)->toHaveCount(1);
    
    // Run second time - should skip
    $ran2 = $this->migrator->run([$this->testMigrationPath]);
    expect($ran2)->toHaveCount(0);
})->uses(MigratorTest::class);

it('can rollback migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_migration_table');
    }
};
PHP;

    $this->createTestMigration('create_test_table', $migrationContent);
    
    // Run migration
    $this->migrator->run([$this->testMigrationPath]);
    
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_migration_table'))->toBe(true);
    
    // Rollback
    $rolledBack = $this->migrator->rollback(1);
    expect($rolledBack)->toHaveCount(1);
    expect($schema->hasTable('test_migration_table'))->toBe(false);
})->uses(MigratorTest::class);

it('can reset all migrations', function () {
    $migration1Content = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_table_1', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_table_1');
    }
};
PHP;

    $migration2Content = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_table_2', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_table_2');
    }
};
PHP;

    sleep(1);
    $this->createTestMigration('create_table_1', $migration1Content);
    sleep(1);
    $this->createTestMigration('create_table_2', $migration2Content);
    
    // Run migrations
    $this->migrator->run([$this->testMigrationPath]);
    
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_table_1'))->toBe(true);
    expect($schema->hasTable('test_table_2'))->toBe(true);
    
    // Reset all
    $reset = $this->migrator->reset();
    expect($reset)->toHaveCount(2);
    expect($schema->hasTable('test_table_1'))->toBe(false);
    expect($schema->hasTable('test_table_2'))->toBe(false);
    
    // Verify migration table is empty
    $result = $connection->fetchOne("SELECT COUNT(*) as count FROM migrations");
    expect($result['count'])->toBe(0);
})->uses(MigratorTest::class);

it('can refresh migrations', function () {
    $migrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('test_migration_table');
    }
};
PHP;

    $this->createTestMigration('create_test_table', $migrationContent);
    
    // Run migration first
    $this->migrator->run([$this->testMigrationPath]);
    
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    expect($schema->hasTable('test_migration_table'))->toBe(true);
    
    // Refresh (reset + run)
    $refreshed = $this->migrator->refresh([$this->testMigrationPath]);
    expect($refreshed)->toHaveCount(1);
    expect($schema->hasTable('test_migration_table'))->toBe(true);
})->uses(MigratorTest::class);

it('handles migration errors with transactions', function () {
    $badMigrationContent = <<<'PHP'
<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        
        // This should cause an error - trying to create same table twice
        $this->create('test_migration_table', function (Blueprint $table) {
            $table->id();
            $table->string('other_name');
        });
    }
};
PHP;

    $this->createTestMigration('bad_migration', $badMigrationContent);
    
    $connection = $this->app->make('db');
    $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
    
    try {
        $this->migrator->run([$this->testMigrationPath]);
        expect(false)->toBe(true, 'Should have thrown an exception');
    } catch (Exception $e) {
        // Verify transaction rolled back - table should not exist
        expect($schema->hasTable('test_migration_table'))->toBe(false);
        
        // Verify migration was not logged
        $result = $connection->fetchOne("SELECT COUNT(*) as count FROM migrations");
        expect($result['count'])->toBe(0);
    }
})->uses(MigratorTest::class);