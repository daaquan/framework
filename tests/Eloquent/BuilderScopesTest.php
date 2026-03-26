<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('eloquent builder scope integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent builder scope integration tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Builder;
use Phare\Eloquent\Model;
use Tests\Mock\Models\User;

class ScopedUser extends User
{
    protected ?string $table = 'users';

    protected array $fillable = [
        'id',
        'device_id',
        'name',
        'email',
        'status',
        'tenant_id',
        'email_verified_at',
        'password',
        'birthday',
    ];

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('status', 'active');
    }
}

beforeEach(function () {
    resetEloquentModelState(ScopedUser::class);

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
        $table->string('status')->nullable();
        $table->foreignId('tenant_id')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->date('birthday')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });
});

it('registers and applies a global scope', function () {
    ScopedUser::addGlobalScope('active', fn (Builder $builder) => $builder->where('status', 'active'));

    createScopedUser(['email' => 'active@example.com', 'status' => 'active']);
    createScopedUser(['email' => 'inactive@example.com', 'status' => 'inactive']);

    $users = ScopedUser::query()->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->readAttribute('email'))->toBe('active@example.com')
        ->and(ScopedUser::hasGlobalScope('active'))->toBeTrue();
});

it('removes a global scope from a query', function () {
    ScopedUser::addGlobalScope('active', fn (Builder $builder) => $builder->where('status', 'active'));

    createScopedUser(['email' => 'active@example.com', 'status' => 'active']);
    createScopedUser(['email' => 'inactive@example.com', 'status' => 'inactive']);

    $users = ScopedUser::query()
        ->withoutGlobalScope('active')
        ->orderBy('email')
        ->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]->readAttribute('email'))->toBe('active@example.com')
        ->and($users[1]->readAttribute('email'))->toBe('inactive@example.com');
});

it('delegates local scopes through builder magic calls', function () {
    createScopedUser(['email' => 'active@example.com', 'status' => 'active']);
    createScopedUser(['email' => 'inactive@example.com', 'status' => 'inactive']);

    $users = ScopedUser::query()->active()->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->readAttribute('status'))->toBe('active');
});

it('stacks multiple global scopes', function () {
    ScopedUser::addGlobalScope('active', fn (Builder $builder) => $builder->where('status', 'active'));
    ScopedUser::addGlobalScope('tenant', fn (Builder $builder) => $builder->where('tenant_id', 10));

    createScopedUser(['email' => 'tenant-active@example.com', 'status' => 'active', 'tenant_id' => 10]);
    createScopedUser(['email' => 'tenant-inactive@example.com', 'status' => 'inactive', 'tenant_id' => 10]);
    createScopedUser(['email' => 'other-tenant@example.com', 'status' => 'active', 'tenant_id' => 11]);

    $users = ScopedUser::query()->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->readAttribute('email'))->toBe('tenant-active@example.com');
});

function createScopedUser(array $attributes): ScopedUser
{
    $user = new ScopedUser();
    $user->fill(array_merge([
        'name' => 'Scoped User',
        'password' => 'secret',
    ], $attributes));
    $user->create();

    return $user;
}

function resetEloquentModelState(string $class): void
{
    $reflection = new ReflectionClass(Model::class);

    foreach (['booted', 'initializing', 'traitInitializers', 'globalScopes'] as $property) {
        $value = $reflection->getProperty($property)->getValue();
        unset($value[$class]);
        $reflection->getProperty($property)->setValue(null, $value);
    }
}
