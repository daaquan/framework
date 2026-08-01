<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('through relationship integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for through relationship integration tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Model;

if (!class_exists(ThroughCountryModel::class)) {
    class ThroughCountryModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'through_countries';

        protected array $fillable = ['id', 'name'];

        protected array $casts = ['id' => 'int'];

        public function users()
        {
            return $this->hasMany(ThroughUserModel::class, 'country_id');
        }

        public function featuredProfile()
        {
            return $this->hasOneThrough(ThroughProfileModel::class, ThroughUserModel::class, 'country_id', 'user_id', 'id', 'id');
        }

        public function posts()
        {
            return $this->hasManyThrough(ThroughPostModel::class, ThroughUserModel::class, 'country_id', 'user_id', 'id', 'id');
        }
    }
}

if (!class_exists(ThroughUserModel::class)) {
    class ThroughUserModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'through_users';

        protected array $fillable = ['id', 'country_id', 'name'];

        protected array $casts = [
            'id' => 'int',
            'country_id' => 'int',
        ];

        public function country()
        {
            return $this->belongsTo(ThroughCountryModel::class, 'country_id');
        }

        public function profile()
        {
            return $this->hasOne(ThroughProfileModel::class, 'user_id');
        }

        public function posts()
        {
            return $this->hasMany(ThroughPostModel::class, 'user_id');
        }
    }
}

if (!class_exists(ThroughProfileModel::class)) {
    class ThroughProfileModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'through_profiles';

        protected array $fillable = ['id', 'user_id', 'bio'];

        protected array $casts = [
            'id' => 'int',
            'user_id' => 'int',
        ];

        public function user()
        {
            return $this->belongsTo(ThroughUserModel::class, 'user_id');
        }
    }
}

if (!class_exists(ThroughPostModel::class)) {
    class ThroughPostModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'through_posts';

        protected array $fillable = ['id', 'user_id', 'title', 'active'];

        protected array $casts = [
            'id' => 'int',
            'user_id' => 'int',
            'active' => 'int',
        ];

        public function user()
        {
            return $this->belongsTo(ThroughUserModel::class, 'user_id');
        }

        public function comments()
        {
            return $this->hasMany(ThroughCommentModel::class, 'post_id');
        }
    }
}

if (!class_exists(ThroughCommentModel::class)) {
    class ThroughCommentModel extends Model
    {
        protected ?string $connection = 'db';

        protected ?string $table = 'through_comments';

        protected array $fillable = ['id', 'post_id', 'body'];

        protected array $casts = [
            'id' => 'int',
            'post_id' => 'int',
        ];

        public function post()
        {
            return $this->belongsTo(ThroughPostModel::class, 'post_id');
        }
    }
}

beforeEach(function () {
    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    foreach ([
        'through_comments',
        'through_posts',
        'through_profiles',
        'through_users',
        'through_countries',
    ] as $table) {
        if ($schema->hasTable($table)) {
            $connection->execute('DROP TABLE ' . $table);
        }
    }

    $schema->create('through_countries', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $schema->create('through_users', function (Blueprint $table) {
        $table->id();
        $table->foreignId('country_id');
        $table->string('name');
    });

    $schema->create('through_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('bio');
    });

    $schema->create('through_posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('title');
        $table->integer('active')->default(1);
    });

    $schema->create('through_comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('post_id');
        $table->string('body');
    });
});

it('supports has one through lazy and eager loading', function () {
    $country = new ThroughCountryModel();
    $country->create(['name' => 'Japan']);

    $user = $country->users()->create(['name' => 'Aiko']);
    $profile = $user->profile()->create(['bio' => 'Maintainer']);

    $fresh = ThroughCountryModel::where('id', $country->id)->first();
    $countries = ThroughCountryModel::with('featuredProfile')->orderBy('id')->get();

    expect($fresh->featuredProfile)->toBeInstanceOf(ThroughProfileModel::class)
        ->and($fresh->featuredProfile->bio)->toBe('Maintainer')
        ->and($countries[0]->relationLoaded('featuredProfile'))->toBeTrue()
        ->and($countries[0]->featuredProfile->id)->toBe($profile->id);
});

it('supports has many through lazy and eager loading', function () {
    $country = new ThroughCountryModel();
    $country->create(['name' => 'Japan']);

    $firstUser = $country->users()->create(['name' => 'Aiko']);
    $secondUser = $country->users()->create(['name' => 'Ren']);

    $firstUser->posts()->create(['title' => 'One', 'active' => 1]);
    $firstUser->posts()->create(['title' => 'Two', 'active' => 0]);
    $secondUser->posts()->create(['title' => 'Three', 'active' => 1]);

    $fresh = ThroughCountryModel::where('id', $country->id)->first();
    $countries = ThroughCountryModel::with('posts')->orderBy('id')->get();

    expect($fresh->posts)->toHaveCount(3)
        ->and($countries[0]->relationLoaded('posts'))->toBeTrue()
        ->and($countries[0]->posts)->toHaveCount(3);
});

it('supports nested eager loading with dot notation', function () {
    $country = new ThroughCountryModel();
    $country->create(['name' => 'Japan']);

    $user = $country->users()->create(['name' => 'Aiko']);
    $firstPost = $user->posts()->create(['title' => 'One', 'active' => 1]);
    $secondPost = $user->posts()->create(['title' => 'Two', 'active' => 1]);

    $firstPost->comments()->create(['body' => 'First']);
    $firstPost->comments()->create(['body' => 'Second']);
    $secondPost->comments()->create(['body' => 'Third']);

    $users = ThroughUserModel::with('posts.comments')->orderBy('id')->get();

    expect($users[0]->relationLoaded('posts'))->toBeTrue()
        ->and($users[0]->posts)->toHaveCount(2)
        ->and($users[0]->posts[0]->relationLoaded('comments'))->toBeTrue()
        ->and($users[0]->posts[0]->comments)->toHaveCount(2)
        ->and($users[0]->posts[1]->comments)->toHaveCount(1);
});

it('supports constrained eager loading with closures', function () {
    $country = new ThroughCountryModel();
    $country->create(['name' => 'Japan']);

    $user = $country->users()->create(['name' => 'Aiko']);
    $user->posts()->create(['title' => 'Active', 'active' => 1]);
    $user->posts()->create(['title' => 'Inactive', 'active' => 0]);

    $users = ThroughUserModel::with([
        'posts' => fn ($query) => $query->where('active', 1)->orderBy('id'),
    ])->orderBy('id')->get();

    expect($users[0]->posts)->toHaveCount(1)
        ->and($users[0]->posts[0]->title)->toBe('Active');
});
