<?php

use Phalcon\Mvc\Model\Resultset;
use Tests\Mock\Models\User;

beforeEach(function () {
    /** @var \Phare\Database\MySql\DatabaseManager $dbManager */
    $dbManager = Phalcon\Di\Di::getDefault()->getShared('dbManager');

    // Migration
    $db = $dbManager->getConnection(['driver' => 'sqlite', 'database' => 'db']);
    foreach (glob(database_path('migrations/*.sql')) as $file) {
        $db->query(file_get_contents($file));
    }
});

afterEach(function () {
    // Perform cleanup after tests, e.g., database rollbacks.
});

it('tests fillable attributes', function () {
    $user = new User();
    $user->fill([
        'id' => 1,
        'email' => 'test@example.com',
        'name' => 'Test User',
        'password' => 'password',
        'email_verified_at' => $date = \Pest\Faker\fake()->dateTime(),
    ]);

    expect($user->email)->toBe('test@example.com', 'User email does not match')
        ->and($user->name)->toBe('Test User', 'User name does not match')
        ->and($user->email_verified_at)->toBe($date->format('Y-m-d H:i:s'), 'Email verified date does not match');
});

it('tests model can create data', function () {
    $email = \Pest\Faker\fake()->email();
    $name = \Pest\Faker\fake()->name();
    $password = \Pest\Faker\fake()->password();
    $email_verified_at = \Pest\Faker\fake()->dateTime();

    $user = new User();
    $user->fill(compact('id', 'email', 'name', 'password', 'email_verified_at'));

    expect($user->create())->toBeTrue('User should be created');

    $created = User::where('email', $email)->first();
    expect($created)->toBeInstanceOf(User::class, 'The created record is not an instance of User')
        ->and($created->email)->toBe($email, 'The email of the created record does not match')
        ->and($created->name)->toBe($name, 'The name of the created record does not match')
        ->and($created->email_verified_at)->toBe($email_verified_at->format('Y-m-d H:i:s'), 'The email verified date does not match')
        ->and(true)->toBe(password_verify($password, $created->password), 'The password of the created record does not match');
});

it('tests model can update data', function () {
    $initialUser = new User();
    $initialUser->fill([
        'email' => \Pest\Faker\fake()->email(),
        'name' => 'Initial Name',
        'password' => \Pest\Faker\fake()->password(),
        'email_verified_at' => \Pest\Faker\fake()->dateTime(),
    ]);
    $initialUser->create();

    $user = User::where('email', $initialUser->email)->first();
    expect($user)->not->toBeNull('Failed to retrieve user for update');

    $user->name = 'Updated Name';
    $user->update();

    $updated = User::where('email', $initialUser->email)->first();
    expect($updated)->toBeInstanceOf(User::class, 'The updated record is not an instance of User')
        ->and($updated->name)->toBe('Updated Name', 'The name of the updated record does not match');
});

it('tests basic where method usage', function () {
    $initialUser = new User();
    $initialUser->fill([
        'email' => \Pest\Faker\fake()->email(),
        'name' => 'Where Test User',
        'password' => \Pest\Faker\fake()->password(),
        'email_verified_at' => \Pest\Faker\fake()->dateTime(),
    ]);
    $initialUser->create();

    $user = User::where('email', $initialUser->email)->first();

    expect($user)->toBeInstanceOf(User::class, 'User retrieval failed using where method')
        ->and($user->name)->toBe('Where Test User', 'The name does not match using where method');
});

it('tests basic orWhere method usage', function () {
    $initialUser = new User();
    $initialUser->fill([
        'email' => \Pest\Faker\fake()->email(),
        'name' => 'OrWhere Test User',
        'password' => \Pest\Faker\fake()->password(),
        'email_verified_at' => \Pest\Faker\fake()->dateTime(),
    ]);
    $initialUser->create();

    $user = User::where('id', '<', '0')
        ->orWhere('email', $initialUser->email)
        ->first();

    expect($user)->toBeInstanceOf(User::class, 'User retrieval failed using orWhere method')
        ->and($user->name)->toBe('OrWhere Test User', 'The name does not match using orWhere method');
});

it('tests where method usage with closure', function () {
    $initialUser = new User();
    $initialUser->fill([
        'email' => \Pest\Faker\fake()->email(),
        'name' => 'Closure Test User',
        'password' => \Pest\Faker\fake()->password(),
        'email_verified_at' => \Pest\Faker\fake()->dateTime(),
    ]);
    $initialUser->create();

    $users = User::query()->where(function ($query) use ($initialUser) {
        $query->where('id', '<', 0)
            ->orWhere('email', '=', $initialUser->email);
    })->get();

    expect($users)->toBeInstanceOf(Resultset::class, 'Failed to retrieve users using closure in where method')
        ->and(count($users))->toBe(1, 'Unexpected number of users retrieved')
        ->and($users[0]->name)->toBe('Closure Test User', 'The name does not match using closure in where method');
});

it('tests model can delete data', function () {
    $user = new User();
    $user->fill([
        'email' => \Pest\Faker\fake()->email(),
        'name' => 'Delete Test User',
        'password' => \Pest\Faker\fake()->password(),
        'email_verified_at' => \Pest\Faker\fake()->dateTime(),
    ]);
    $user->create();

    $found = User::where('email', $user->email)->first();
    expect($found)->not->toBeNull('Failed to retrieve user for delete');

    $id = $found->id;
    $found->delete();

    $softDeleted = User::where('email', $user->email)->first();
    expect($softDeleted)->toBeNull('Failed to soft delete user');

    $found->restore();

    $restored = User::where('email', $user->email)->first();
    expect($restored)->not->toBeNull('Failed to restore user')
        ->and($restored->id)->toBe($id, 'Failed to restore user id');

    $restored->forceDelete();

    $deleted = User::where('email', $user->email)->first();
    expect($deleted)->toBeNull('Failed to force delete user');
});
