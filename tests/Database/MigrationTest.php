<?php

use Phare\Database\Migration;
use Phare\Database\Migrator;
use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    protected Migrator $migrator;
    protected SchemaBuilder $schema;

    protected function setUp(): void
    {
        parent::setUp();
        
        $connection = $this->app->make('db');
        $this->migrator = new Migrator($this->app, $connection);
        $this->schema = new SchemaBuilder($connection);
        
        // Clean up any existing test tables
        if ($this->schema->hasTable('test_table')) {
            $this->schema->drop('test_table');
        }
        if ($this->schema->hasTable('users')) {
            $this->schema->drop('users');
        }
    }

    public function test_can_create_table()
    {
        $this->schema->create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $this->assertTrue($this->schema->hasTable('test_table'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'id'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'name'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'email'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'created_at'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'updated_at'));
    }

    public function test_can_drop_table()
    {
        $this->schema->create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->assertTrue($this->schema->hasTable('test_table'));

        $this->schema->drop('test_table');

        $this->assertFalse($this->schema->hasTable('test_table'));
    }

    public function test_can_add_columns_to_existing_table()
    {
        $this->schema->create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->table('test_table', function (Blueprint $table) {
            $table->string('email');
            $table->integer('age')->nullable();
        });

        $this->assertTrue($this->schema->hasColumn('test_table', 'email'));
        $this->assertTrue($this->schema->hasColumn('test_table', 'age'));
    }

    public function test_migration_class_works()
    {
        $migration = new class($this->schema) extends Migration {
            public function up(): void
            {
                $this->create('users', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->string('email')->unique();
                    $table->timestamp('email_verified_at')->nullable();
                    $table->string('password');
                    $table->timestamps();
                });
            }

            public function down(): void
            {
                $this->dropIfExists('users');
            }
        };

        $migration->up();

        $this->assertTrue($this->schema->hasTable('users'));
        $this->assertTrue($this->schema->hasColumn('users', 'name'));
        $this->assertTrue($this->schema->hasColumn('users', 'email'));

        $migration->down();

        $this->assertFalse($this->schema->hasTable('users'));
    }

    public function test_can_create_foreign_key()
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->assertTrue($this->schema->hasTable('posts'));
        $this->assertTrue($this->schema->hasColumn('posts', 'user_id'));
    }

    protected function tearDown(): void
    {
        // Clean up test tables
        if ($this->schema->hasTable('posts')) {
            $this->schema->drop('posts');
        }
        if ($this->schema->hasTable('users')) {
            $this->schema->drop('users');
        }
        if ($this->schema->hasTable('test_table')) {
            $this->schema->drop('test_table');
        }

        parent::tearDown();
    }
}