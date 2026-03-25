<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('eloquent relationship integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent relationship integration tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Tests\Mock\Models\Label;
use Tests\Mock\Models\Post;
use Tests\Mock\Models\Profile;
use Tests\Mock\Models\User;

beforeEach(function () {
    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    foreach (['labels', 'profiles', 'posts', 'users'] as $table) {
        if ($schema->hasTable($table)) {
            $connection->execute('DROP TABLE ' . $table);
        }
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

    $schema->create('profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('bio');
        $table->timestamps();
    });

    $schema->create('posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('title');
        $table->timestamps();
    });

    $schema->create('labels', function (Blueprint $table) {
        $table->id();
        $table->foreignId('labelable_id');
        $table->string('labelable_type');
        $table->string('name');
        $table->timestamps();
    });
});

it('lazy loads has one and has many relationships', function () {
    $user = new User();
    $user->fill([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);
    $user->create();

    $profile = $user->profile()->create(['bio' => 'Builder']);
    $firstPost = $user->posts()->create(['title' => 'First post']);
    $secondPost = $user->posts()->create(['title' => 'Second post']);

    $reloaded = User::where('id', $user->id)->first();

    expect($reloaded->profile)->toBeInstanceOf(Profile::class)
        ->and($reloaded->profile->id)->toBe($profile->id)
        ->and($reloaded->posts)->toHaveCount(2)
        ->and($reloaded->posts->first()->user_id)->toBe($user->id)
        ->and($reloaded->posts->last()->id)->toBe($secondPost->id);
});

it('resolves belongs to and can associate a parent model', function () {
    $user = new User();
    $user->fill([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret',
    ]);
    $user->create();

    $post = new Post();
    $post->fill(['title' => 'Associated post']);
    $post->user()->associate($user);
    $post->create();

    $stored = Post::where('id', $post->id)->first();

    expect($stored->user)->toBeInstanceOf(User::class)
        ->and($stored->user->id)->toBe($user->id)
        ->and($stored->user_id)->toBe($user->id);
});

it('eager loads requested relationships with with()', function () {
    $first = new User();
    $first->fill([
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'password' => 'secret',
    ]);
    $first->create();
    $first->profile()->create(['bio' => 'First profile']);
    $first->posts()->create(['title' => 'Carol post']);

    $second = new User();
    $second->fill([
        'name' => 'Dave',
        'email' => 'dave@example.com',
        'password' => 'secret',
    ]);
    $second->create();
    $second->posts()->create(['title' => 'Dave post 1']);
    $second->posts()->create(['title' => 'Dave post 2']);

    $users = User::with(['profile', 'posts'])->orderBy('id')->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]->relationLoaded('profile'))->toBeTrue()
        ->and($users[0]->relationLoaded('posts'))->toBeTrue()
        ->and($users[0]->profile)->toBeInstanceOf(Profile::class)
        ->and($users[0]->posts)->toHaveCount(1)
        ->and($users[1]->profile)->toBeNull()
        ->and($users[1]->posts)->toHaveCount(2);
});

it('supports load() on an existing model instance', function () {
    $user = new User();
    $user->fill([
        'name' => 'Eve',
        'email' => 'eve@example.com',
        'password' => 'secret',
    ]);
    $user->create();
    $user->posts()->create(['title' => 'Loaded later']);

    $fresh = User::where('id', $user->id)->first();
    $fresh->load('posts');

    expect($fresh->relationLoaded('posts'))->toBeTrue()
        ->and($fresh->posts)->toHaveCount(1)
        ->and($fresh->posts->first()->title)->toBe('Loaded later');
});

it('supports morph many and morph to relationships', function () {
    $user = new User();
    $user->fill([
        'name' => 'Frank',
        'email' => 'frank@example.com',
        'password' => 'secret',
    ]);
    $user->create();

    $label = $user->labels()->create(['name' => 'vip']);

    $stored = Label::where('id', $label->id)->first();

    expect($stored->labelable)->toBeInstanceOf(User::class)
        ->and($stored->labelable->id)->toBe($user->id)
        ->and($stored->labelable_type)->toBe(User::class)
        ->and($stored->labelable_id)->toBe($user->id);
});
