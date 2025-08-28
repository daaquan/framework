<?php

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    protected SchemaBuilder $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->app->make('db');
        $this->schema = new SchemaBuilder($connection);

        // Clean up test tables
        $testTables = ['test_schema_table', 'test_users', 'test_posts'];
        foreach ($testTables as $table) {
            if ($this->schema->hasTable($table)) {
                $this->schema->drop($table);
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up test tables
        $testTables = ['test_schema_table', 'test_users', 'test_posts'];
        foreach ($testTables as $table) {
            if ($this->schema->hasTable($table)) {
                $this->schema->drop($table);
            }
        }

        parent::tearDown();
    }
}

it('can check if table exists', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    expect($schema->hasTable('non_existent_table'))->toBe(false);

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    expect($schema->hasTable('test_schema_table'))->toBe(true);
})->uses(SchemaBuilderTest::class);

it('can check if column exists', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
    });

    expect($schema->hasColumn('test_schema_table', 'name'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'email'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'non_existent'))->toBe(false);
})->uses(SchemaBuilderTest::class);

it('can get column listing', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    $columns = $schema->getColumnListing('test_schema_table');

    expect($columns)->toContain('id');
    expect($columns)->toContain('name');
    expect($columns)->toContain('email');
    expect($columns)->toContain('created_at');
    expect($columns)->toContain('updated_at');
})->uses(SchemaBuilderTest::class);

it('can rename table', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    expect($schema->hasTable('test_schema_table'))->toBe(true);
    expect($schema->hasTable('renamed_table'))->toBe(false);

    $schema->rename('test_schema_table', 'renamed_table');

    expect($schema->hasTable('test_schema_table'))->toBe(false);
    expect($schema->hasTable('renamed_table'))->toBe(true);

    // Clean up
    $schema->drop('renamed_table');
})->uses(SchemaBuilderTest::class);

it('can drop table if exists', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    // Should not throw error even if table doesn't exist
    $schema->dropIfExists('non_existent_table');

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    expect($schema->hasTable('test_schema_table'))->toBe(true);

    $schema->dropIfExists('test_schema_table');

    expect($schema->hasTable('test_schema_table'))->toBe(false);
})->uses(SchemaBuilderTest::class);

it('can modify existing table', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    expect($schema->hasColumn('test_schema_table', 'email'))->toBe(false);
    expect($schema->hasColumn('test_schema_table', 'age'))->toBe(false);

    $schema->table('test_schema_table', function (Blueprint $table) {
        $table->string('email');
        $table->integer('age')->nullable();
    });

    expect($schema->hasColumn('test_schema_table', 'email'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'age'))->toBe(true);
})->uses(SchemaBuilderTest::class);

it('can create table with foreign key constraints', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
    });

    $schema->create('test_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->foreignId('user_id');
        $table->foreign('user_id')->references('id')->on('test_users')->cascadeOnDelete();
        $table->timestamps();
    });

    expect($schema->hasTable('test_users'))->toBe(true);
    expect($schema->hasTable('test_posts'))->toBe(true);
    expect($schema->hasColumn('test_posts', 'user_id'))->toBe(true);
})->uses(SchemaBuilderTest::class);

it('can create table with various column types and modifiers', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->comment('User name');
        $table->string('email')->unique();
        $table->text('bio')->nullable();
        $table->integer('age')->unsigned();
        $table->decimal('balance', 10, 2)->default(0.00);
        $table->boolean('is_active')->default(true);
        $table->date('birth_date')->nullable();
        $table->timestamp('last_login')->nullable();
        $table->json('preferences')->nullable();
        $table->timestamps();
    });

    expect($schema->hasTable('test_schema_table'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'name'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'email'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'bio'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'age'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'balance'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'is_active'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'birth_date'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'last_login'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'preferences'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'created_at'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'updated_at'))->toBe(true);
})->uses(SchemaBuilderTest::class);

it('can create enum columns', function () {
    $schema = new SchemaBuilder($this->app->make('db'));

    $schema->create('test_schema_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
    });

    expect($schema->hasTable('test_schema_table'))->toBe(true);
    expect($schema->hasColumn('test_schema_table', 'status'))->toBe(true);
})->uses(SchemaBuilderTest::class);
