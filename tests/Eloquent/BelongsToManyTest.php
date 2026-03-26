<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('belongs to many tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for belongs to many tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Tests\Mock\Models\Role;
use Tests\Mock\Models\User;

beforeEach(function () {
    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    foreach (['role_user', 'roles', 'users'] as $table) {
        if ($schema->hasTable($table)) {
            $connection->execute('DROP TABLE ' . $table);
        }
    }

    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    $schema->create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $schema->create('role_user', function (Blueprint $table) {
        $table->foreignId('user_id');
        $table->foreignId('role_id');
        $table->boolean('active')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

function makeUser(string $name = 'Alice', string $email = 'alice@example.com'): User
{
    $user = new User();
    $user->fill([
        'name' => $name,
        'email' => $email,
        'password' => 'secret',
    ]);
    $user->create();

    return $user;
}

function makeRole(string $name): Role
{
    $role = new Role();
    $role->fill(['name' => $name]);
    $role->create();

    return $role;
}

it('loads a basic many to many relationship', function () {
    $user = makeUser();
    $admin = makeRole('admin');
    $editor = makeRole('editor');

    $user->roles()->attach($admin->id, ['active' => 1]);
    $user->roles()->attach($editor->id, ['active' => 0]);

    $fresh = User::where('id', $user->id)->first();

    expect($fresh->roles)->toHaveCount(2)
        ->and($fresh->roles->first()->pivot->user_id)->toBe($user->id)
        ->and($fresh->roles->first()->pivot->role_id)->not->toBeNull();
});

it('attaches a pivot record', function () {
    $user = makeUser();
    $role = makeRole('admin');

    $user->roles()->attach($role->id, ['active' => 1]);

    $rows = $this->app->make('db')->fetchAll('SELECT * FROM role_user');

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['user_id'])->toBe($user->id)
        ->and((int) $rows[0]['role_id'])->toBe($role->id)
        ->and((int) $rows[0]['active'])->toBe(1);
});

it('detaches pivot records', function () {
    $user = makeUser();
    $admin = makeRole('admin');
    $editor = makeRole('editor');

    $user->roles()->attach($admin->id);
    $user->roles()->attach($editor->id);

    $deleted = $user->roles()->detach([$admin->id]);

    $rows = $this->app->make('db')->fetchAll('SELECT * FROM role_user ORDER BY role_id');

    expect($deleted)->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and((int) $rows[0]['role_id'])->toBe($editor->id);
});

it('syncs pivot records', function () {
    $user = makeUser();
    $admin = makeRole('admin');
    $editor = makeRole('editor');
    $viewer = makeRole('viewer');

    $user->roles()->attach($admin->id, ['active' => 1]);
    $user->roles()->attach($editor->id, ['active' => 0]);

    $changes = $user->roles()->sync([
        $editor->id => ['active' => 1],
        $viewer->id => ['active' => 1],
    ]);

    $rows = $this->app->make('db')->fetchAll('SELECT role_id, active FROM role_user ORDER BY role_id');

    expect($changes['attached'])->toBe([$viewer->id])
        ->and($changes['detached'])->toBe([$admin->id])
        ->and($changes['updated'])->toBe([$editor->id])
        ->and($rows)->toHaveCount(2)
        ->and((int) $rows[0]['active'])->toBe(1)
        ->and((int) $rows[1]['active'])->toBe(1);
});

it('toggles pivot records', function () {
    $user = makeUser();
    $admin = makeRole('admin');
    $editor = makeRole('editor');

    $user->roles()->attach($admin->id);

    $changes = $user->roles()->toggle([$admin->id, $editor->id]);

    $rows = $this->app->make('db')->fetchAll('SELECT role_id FROM role_user ORDER BY role_id');

    expect($changes['attached'])->toBe([$editor->id])
        ->and($changes['detached'])->toBe([$admin->id])
        ->and($rows)->toHaveCount(1)
        ->and((int) $rows[0]['role_id'])->toBe($editor->id);
});

it('hydrates extra pivot columns and timestamps', function () {
    $user = makeUser();
    $role = makeRole('admin');

    $user->roles()->attach($role->id, ['active' => 1]);

    $fresh = User::where('id', $user->id)->first();
    $attached = $fresh->roles->first();

    expect($attached->pivot->active)->toBe(1)
        ->and($attached->pivot->created_at)->not->toBeNull()
        ->and($attached->pivot->updated_at)->not->toBeNull();
});

it('eager loads many to many relations', function () {
    $first = makeUser('First', 'first@example.com');
    $second = makeUser('Second', 'second@example.com');
    $admin = makeRole('admin');
    $editor = makeRole('editor');

    $first->roles()->attach($admin->id);
    $second->roles()->attach($editor->id);

    $users = User::with('roles')->orderBy('id')->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]->relationLoaded('roles'))->toBeTrue()
        ->and($users[0]->roles)->toHaveCount(1)
        ->and($users[0]->roles->first()->name)->toBe('admin')
        ->and($users[1]->roles->first()->name)->toBe('editor');
});

it('supports a custom pivot accessor name', function () {
    $user = makeUser();
    $role = makeRole('admin');

    $user->rolesWithMembership()->attach($role->id, ['active' => 1]);

    $fresh = User::where('id', $user->id)->first();
    $roleModel = $fresh->rolesWithMembership->first();

    expect($roleModel->membership)->not->toBeNull()
        ->and($roleModel->membership->active)->toBe(1)
        ->and($roleModel->getRelation('pivot'))->toBeNull();
});
