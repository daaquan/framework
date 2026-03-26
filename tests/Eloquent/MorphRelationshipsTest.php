<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('morph relationship integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for morph relationship integration tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Model;

if (!class_exists(MorphUserModel::class)) {
    class MorphUserModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'morph_users';

        protected array $fillable = ['id', 'name'];

        protected array $casts = ['id' => 'int'];

        public function avatar()
        {
            return $this->morphOne(MorphImageModel::class, 'imageable');
        }
    }
}

if (!class_exists(MorphPostModel::class)) {
    class MorphPostModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'morph_posts';

        protected array $fillable = ['id', 'title'];

        protected array $casts = ['id' => 'int'];

        public function avatar()
        {
            return $this->morphOne(MorphImageModel::class, 'imageable');
        }
    }
}

if (!class_exists(MorphImageModel::class)) {
    class MorphImageModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'morph_images';

        protected array $fillable = ['id', 'imageable_type', 'imageable_id', 'path'];

        protected array $casts = [
            'id' => 'int',
            'imageable_id' => 'int',
        ];

        public function imageable()
        {
            return $this->morphTo();
        }
    }
}

beforeEach(function () {
    Model::morphMap([], false);

    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    foreach (['morph_images', 'morph_posts', 'morph_users'] as $table) {
        if ($schema->hasTable($table)) {
            $connection->execute('DROP TABLE ' . $table);
        }
    }

    $schema->create('morph_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $schema->create('morph_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    $schema->create('morph_images', function (Blueprint $table) {
        $table->id();
        $table->foreignId('imageable_id');
        $table->string('imageable_type');
        $table->string('path');
    });
});

afterEach(function () {
    Model::morphMap([], false);
});

it('supports morph one lazy access and eager loading', function () {
    $first = new MorphUserModel();
    $first->create(['name' => 'Alice']);
    $first->avatar()->create(['path' => 'alice.jpg']);

    $second = new MorphUserModel();
    $second->create(['name' => 'Bob']);

    $reloaded = MorphUserModel::where('id', $first->id)->first();
    $users = MorphUserModel::with('avatar')->orderBy('id')->get();

    expect($reloaded->avatar)->toBeInstanceOf(MorphImageModel::class)
        ->and($reloaded->avatar->path)->toBe('alice.jpg')
        ->and($users[0]->relationLoaded('avatar'))->toBeTrue()
        ->and($users[0]->avatar)->toBeInstanceOf(MorphImageModel::class)
        ->and($users[1]->avatar)->toBeNull();
});

it('eager loads morph to relations across multiple target types', function () {
    $user = new MorphUserModel();
    $user->create(['name' => 'Alice']);

    $post = new MorphPostModel();
    $post->create(['title' => 'Hello']);

    $user->avatar()->create(['path' => 'user.jpg']);
    $post->avatar()->create(['path' => 'post.jpg']);

    $images = MorphImageModel::with('imageable')->orderBy('id')->get();

    expect($images)->toHaveCount(2)
        ->and($images[0]->relationLoaded('imageable'))->toBeTrue()
        ->and($images[0]->imageable)->toBeInstanceOf(MorphUserModel::class)
        ->and($images[0]->imageable->name)->toBe('Alice')
        ->and($images[1]->imageable)->toBeInstanceOf(MorphPostModel::class)
        ->and($images[1]->imageable->title)->toBe('Hello');
});

it('resolves morph to eager loading through morph map aliases', function () {
    Model::morphMap([
        'user-avatar' => MorphUserModel::class,
        'post-avatar' => MorphPostModel::class,
    ], false);

    $user = new MorphUserModel();
    $user->create(['name' => 'Mapped User']);

    $post = new MorphPostModel();
    $post->create(['title' => 'Mapped Post']);

    $user->avatar()->create(['path' => 'mapped-user.jpg']);
    $post->avatar()->create(['path' => 'mapped-post.jpg']);

    $images = MorphImageModel::with('imageable')->orderBy('id')->get();

    expect($images[0]->imageable_type)->toBe('user-avatar')
        ->and($images[0]->imageable)->toBeInstanceOf(MorphUserModel::class)
        ->and($images[1]->imageable_type)->toBe('post-avatar')
        ->and($images[1]->imageable)->toBeInstanceOf(MorphPostModel::class);
});
