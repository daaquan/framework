<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('schema builder integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for schema builder integration tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;

// Use schema_test_users (not test_users) to avoid conflicts with FactoryTest's global beforeEach scope.
// The beforeEach/afterEach below are scoped to this describe block to prevent cross-test pollution.
describe('SchemaBuilder', function () {
    beforeEach(function () {
        $connection = $this->app->make('db');
        $this->schema = new SchemaBuilder($connection);

        // Clean up test tables
        foreach (['test_schema_table', 'schema_test_users', 'test_posts', 'renamed_table'] as $table) {
            if ($this->schema->hasTable($table)) {
                $this->schema->drop($table);
            }
        }
    });

    afterEach(function () {
        foreach (['test_schema_table', 'schema_test_users', 'test_posts', 'renamed_table'] as $table) {
            if ($this->schema->hasTable($table)) {
                $this->schema->drop($table);
            }
        }
    });

    it('can check if table exists', function () {
        expect($this->schema->hasTable('non_existent_table'))->toBe(false);

        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        expect($this->schema->hasTable('test_schema_table'))->toBe(true);
    });

    it('can check if column exists', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        });

        expect($this->schema->hasColumn('test_schema_table', 'name'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'email'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'non_existent'))->toBe(false);
    });

    it('can get column listing', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $columns = $this->schema->getColumnListing('test_schema_table');

        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('email');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    it('can rename table', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        expect($this->schema->hasTable('test_schema_table'))->toBe(true);
        expect($this->schema->hasTable('renamed_table'))->toBe(false);

        $this->schema->rename('test_schema_table', 'renamed_table');

        expect($this->schema->hasTable('test_schema_table'))->toBe(false);
        expect($this->schema->hasTable('renamed_table'))->toBe(true);
    });

    it('can drop table if exists', function () {
        $this->schema->dropIfExists('non_existent_table');

        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        expect($this->schema->hasTable('test_schema_table'))->toBe(true);

        $this->schema->dropIfExists('test_schema_table');

        expect($this->schema->hasTable('test_schema_table'))->toBe(false);
    });

    it('can modify existing table', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        expect($this->schema->hasColumn('test_schema_table', 'email'))->toBe(false);
        expect($this->schema->hasColumn('test_schema_table', 'age'))->toBe(false);

        $this->schema->table('test_schema_table', function (Blueprint $table) {
            $table->string('email');
            $table->integer('age')->nullable();
        });

        expect($this->schema->hasColumn('test_schema_table', 'email'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'age'))->toBe(true);
    });

    it('can create table with foreign key constraints', function () {
        $this->schema->create('schema_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
        });

        $this->schema->create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('user_id');
            $table->foreign('user_id')->references('id')->on('schema_test_users')->cascadeOnDelete();
            $table->timestamps();
        });

        expect($this->schema->hasTable('schema_test_users'))->toBe(true);
        expect($this->schema->hasTable('test_posts'))->toBe(true);
        expect($this->schema->hasColumn('test_posts', 'user_id'))->toBe(true);
    });

    it('can create table with various column types and modifiers', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
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

        expect($this->schema->hasTable('test_schema_table'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'name'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'email'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'bio'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'age'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'balance'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'is_active'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'birth_date'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'last_login'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'preferences'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'created_at'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'updated_at'))->toBe(true);
    });

    it('can create enum columns', function () {
        $this->schema->create('test_schema_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
        });

        expect($this->schema->hasTable('test_schema_table'))->toBe(true);
        expect($this->schema->hasColumn('test_schema_table', 'status'))->toBe(true);
    });
});
