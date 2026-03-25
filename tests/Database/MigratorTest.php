<?php

use Phare\Database\Migration;
use Phare\Database\Migrator;
use Phare\Database\Schema\SchemaBuilder;

$testMigrationPath = sys_get_temp_dir() . '/test_migrations_' . getmypid();

function createTestMigration(string $path, string $name, string $content): string
{
    $filename = date('Y_m_d_His') . '_' . $name . '.php';
    $filepath = $path . '/' . $filename;
    file_put_contents($filepath, $content);

    return $filepath;
}

beforeEach(function () use ($testMigrationPath) {
    $this->testMigrationPath = $testMigrationPath;

    if (!is_dir($this->testMigrationPath)) {
        mkdir($this->testMigrationPath, 0755, true);
    }

    $connection = $this->app->make('db');
    $this->schema = new SchemaBuilder($connection);

    // Clean up any existing test tables BEFORE creating migrator
    foreach (['test_migration_table', 'test_users_migration', 'test_table_1', 'test_table_2', 'migrations'] as $table) {
        if ($this->schema->hasTable($table)) {
            $this->schema->drop($table);
        }
    }

    $this->migrator = new Migrator($this->app, $connection);
});

afterEach(function () {
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
    foreach (['test_migration_table', 'test_users_migration', 'test_table_1', 'test_table_2', 'migrations'] as $table) {
        if ($this->schema->hasTable($table)) {
            $this->schema->drop($table);
        }
    }
});

it('creates migration table automatically', function () {
    expect($this->schema->hasTable('migrations'))->toBe(true);
});

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

    $migrationFile = createTestMigration($this->testMigrationPath, 'create_test_migration_table', $migrationContent);

    $ran = $this->migrator->run([$this->testMigrationPath]);

    expect($ran)->toHaveCount(1);
    expect($ran[0])->toBe($migrationFile);

    // Verify table was created
    expect($this->schema->hasTable('test_migration_table'))->toBe(true);

    // Verify migration was logged
    $connection = $this->app->make('db');
    $result = $connection->fetchOne('SELECT COUNT(*) as count FROM migrations');
    expect($result['count'])->toBe(1);
});

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

    sleep(1);
    $file1 = createTestMigration($this->testMigrationPath, 'create_users_table', $migration1Content);
    sleep(1);
    $file2 = createTestMigration($this->testMigrationPath, 'add_email_to_users', $migration2Content);

    $ran = $this->migrator->run([$this->testMigrationPath]);

    expect($ran)->toHaveCount(2);

    expect($this->schema->hasTable('test_users_migration'))->toBe(true);
    expect($this->schema->hasColumn('test_users_migration', 'email'))->toBe(true);
});

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

    createTestMigration($this->testMigrationPath, 'create_test_table', $migrationContent);

    $ran1 = $this->migrator->run([$this->testMigrationPath]);
    expect($ran1)->toHaveCount(1);

    $ran2 = $this->migrator->run([$this->testMigrationPath]);
    expect($ran2)->toHaveCount(0);
});

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

    createTestMigration($this->testMigrationPath, 'create_test_table', $migrationContent);

    $this->migrator->run([$this->testMigrationPath]);
    expect($this->schema->hasTable('test_migration_table'))->toBe(true);

    $rolledBack = $this->migrator->rollback(1);
    expect($rolledBack)->toHaveCount(1);
    expect($this->schema->hasTable('test_migration_table'))->toBe(false);
});

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
    createTestMigration($this->testMigrationPath, 'create_table_1', $migration1Content);
    sleep(1);
    createTestMigration($this->testMigrationPath, 'create_table_2', $migration2Content);

    $this->migrator->run([$this->testMigrationPath]);
    expect($this->schema->hasTable('test_table_1'))->toBe(true);
    expect($this->schema->hasTable('test_table_2'))->toBe(true);

    $reset = $this->migrator->reset();
    expect($reset)->toHaveCount(2);
    expect($this->schema->hasTable('test_table_1'))->toBe(false);
    expect($this->schema->hasTable('test_table_2'))->toBe(false);

    $connection = $this->app->make('db');
    $result = $connection->fetchOne('SELECT COUNT(*) as count FROM migrations');
    expect($result['count'])->toBe(0);
});

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

    createTestMigration($this->testMigrationPath, 'create_test_table', $migrationContent);

    $this->migrator->run([$this->testMigrationPath]);
    expect($this->schema->hasTable('test_migration_table'))->toBe(true);

    $refreshed = $this->migrator->refresh([$this->testMigrationPath]);
    expect($refreshed)->toHaveCount(1);
    expect($this->schema->hasTable('test_migration_table'))->toBe(true);
});

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

    createTestMigration($this->testMigrationPath, 'bad_migration', $badMigrationContent);

    try {
        $this->migrator->run([$this->testMigrationPath]);
        expect(false)->toBe(true, 'Should have thrown an exception');
    } catch (Exception $e) {
        expect($this->schema->hasTable('test_migration_table'))->toBe(false);

        $connection = $this->app->make('db');
        $result = $connection->fetchOne('SELECT COUNT(*) as count FROM migrations');
        expect($result['count'])->toBe(0);
    }
});
