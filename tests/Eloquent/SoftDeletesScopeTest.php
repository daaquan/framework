<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('eloquent soft delete scope integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent soft delete scope integration tests.');
    });

    return;
}

use Phalcon\Di\Di;
use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Model;
use Tests\Mock\Models\User;

beforeEach(function () {
    resetSoftDeleteModelState(User::class);

    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    if ($schema->hasTable('users')) {
        $connection->execute('DROP TABLE users');
    }

    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('device_id')->nullable();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->date('birthday')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });
});

it('excludes soft deleted records by default', function () {
    insertSoftDeleteUser('active@example.com');
    insertSoftDeleteUser('trashed@example.com', deletedAt: '2026-01-01 00:00:00');

    $users = User::query()->orderBy('email')->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->readAttribute('email'))->toBe('active@example.com');
});

it('includes soft deleted records with withTrashed', function () {
    insertSoftDeleteUser('active@example.com');
    insertSoftDeleteUser('trashed@example.com', deletedAt: '2026-01-01 00:00:00');

    $users = User::query()->withTrashed()->orderBy('email')->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]->readAttribute('email'))->toBe('active@example.com')
        ->and($users[1]->readAttribute('email'))->toBe('trashed@example.com');
});

it('returns only soft deleted records with onlyTrashed', function () {
    insertSoftDeleteUser('active@example.com');
    insertSoftDeleteUser('trashed@example.com', deletedAt: '2026-01-01 00:00:00');

    $users = User::query()->onlyTrashed()->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->readAttribute('email'))->toBe('trashed@example.com')
        ->and($users[0]->trashed())->toBeTrue();
});

it('restores soft deleted records through the builder macro', function () {
    insertSoftDeleteUser('trashed@example.com', deletedAt: '2026-01-01 00:00:00');

    $restored = User::query()->onlyTrashed()->where('email', 'trashed@example.com')->restore();

    expect($restored)->toBe(1)
        ->and(User::query()->where('email', 'trashed@example.com')->first())->not->toBeNull()
        ->and(User::query()->onlyTrashed()->first())->toBeNull();
});

it('force deletes records permanently through the builder macro', function () {
    insertSoftDeleteUser('trashed@example.com', deletedAt: '2026-01-01 00:00:00');

    $deleted = User::query()->onlyTrashed()->where('email', 'trashed@example.com')->forceDelete();

    expect($deleted)->toBe(1)
        ->and(User::query()->withTrashed()->first())->toBeNull();
});

function createSoftDeleteUser(string $email): User
{
    $user = new User();
    $user->fill([
        'name' => 'Soft Delete User',
        'email' => $email,
        'password' => 'secret',
    ]);
    $user->create();

    $stored = User::query()->where('email', $email)->get();

    return $stored[0];
}

function insertSoftDeleteUser(string $email, ?string $deletedAt = null): void
{
    $connection = Di::getDefault()->get('db');
    $connection->execute(
        'INSERT INTO users (name, email, password, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?)',
        ['Soft Delete User', $email, password_hash('secret', PASSWORD_DEFAULT), nowString(), nowString(), $deletedAt]
    );
}

function nowString(): string
{
    return date('Y-m-d H:i:s');
}

function resetSoftDeleteModelState(string $class): void
{
    $reflection = new ReflectionClass(Model::class);

    foreach (['booted', 'initializing', 'traitInitializers', 'globalScopes'] as $property) {
        $value = $reflection->getProperty($property)->getValue();
        unset($value[$class]);
        $reflection->getProperty($property)->setValue(null, $value);
    }
}
