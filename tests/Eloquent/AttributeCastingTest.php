<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('eloquent attribute casting tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent attribute casting tests.');
    });

    return;
}

use Phare\Collections\Collection;
use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Casts\Attribute as EloquentAttribute;
use Phare\Eloquent\Casts\CastsAttributes;
use Phare\Eloquent\Model;

enum AttributeCastingStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
}

class UppercaseCast implements CastsAttributes
{
    public static int $getCalls = 0;

    public function get($model, string $key, $value, array $attributes): object
    {
        self::$getCalls++;

        return new class(strtoupper((string)$value))
        {
            public function __construct(public string $value) {}
        };
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return strtolower((string)$value);
    }
}

class AttributeCastingModel extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'attribute_casting_models';

    protected array $fillable = [
        'first_name',
        'last_name',
        'settings',
        'meta',
        'options',
        'is_active',
        'score',
        'visits',
        'birthday',
        'status',
        'secret_payload',
        'code',
    ];

    protected array $hidden = ['secret_payload'];

    protected array $appends = ['full_name', 'cached_label', 'uncached_label'];

    protected array $casts = [
        'settings' => 'array',
        'meta' => 'json',
        'options' => 'collection',
        'is_active' => 'bool',
        'score' => 'float',
        'visits' => 'int',
        'birthday' => 'date',
        'status' => AttributeCastingStatus::class,
        'secret_payload' => 'encrypted:array',
        'code' => UppercaseCast::class,
    ];

    public function getLastNameAttribute($value): string
    {
        return strtoupper((string)$value);
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = strtolower((string)$value);
        $this->writeAttribute('last_name', $this->attributes['last_name']);
    }

    protected function firstName(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn ($value) => ucfirst((string)$value),
            set: fn ($value) => strtolower((string)$value),
        );
    }

    protected function fullName(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn ($value, array $attributes) => trim(ucfirst((string)($attributes['first_name'] ?? '')) . ' ' . strtoupper((string)($attributes['last_name'] ?? ''))),
        );
    }

    protected function cachedLabel(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => new ArrayObject(['cached' => true], ArrayObject::ARRAY_AS_PROPS),
        );
    }

    protected function uncachedLabel(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => new ArrayObject(['cached' => false], ArrayObject::ARRAY_AS_PROPS),
        )->withoutObjectCaching();
    }
}

beforeEach(function () {
    UppercaseCast::$getCalls = 0;

    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    if ($schema->hasTable('attribute_casting_models')) {
        $connection->execute('DROP TABLE attribute_casting_models');
    }

    $schema->create('attribute_casting_models', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->text('settings')->nullable();
        $table->text('meta')->nullable();
        $table->text('options')->nullable();
        $table->boolean('is_active')->nullable();
        $table->double('score')->nullable();
        $table->integer('visits')->nullable();
        $table->date('birthday')->nullable();
        $table->string('status')->nullable();
        $table->text('secret_payload')->nullable();
        $table->string('code')->nullable();
    });
});

it('supports legacy mutators and new style attribute mutators', function () {
    $model = new AttributeCastingModel();
    $model->fill([
        'first_name' => 'jANE',
        'last_name' => 'doE',
    ]);

    expect($model->getAttributes()['first_name'])->toBe('jane')
        ->and($model->first_name)->toBe('Jane')
        ->and($model->getAttributes()['last_name'])->toBe('doe')
        ->and($model->last_name)->toBe('DOE')
        ->and($model->full_name)->toBe('Jane DOE');
});

it('supports primitive, enum, encrypted, and collection casts', function () {
    $birthday = new DateTime('2024-02-01 12:34:56');

    $model = new AttributeCastingModel();
    $model->fill([
        'settings' => ['theme' => 'light'],
        'meta' => ['role' => 'admin'],
        'options' => ['a', 'b'],
        'is_active' => 1,
        'score' => '42.75',
        'visits' => '7',
        'birthday' => $birthday,
        'status' => AttributeCastingStatus::Live,
        'secret_payload' => ['token' => 'abc'],
    ]);

    expect($model->settings)->toBe(['theme' => 'light'])
        ->and($model->meta)->toBe(['role' => 'admin'])
        ->and($model->options)->toBeInstanceOf(Collection::class)
        ->and($model->options->toArray())->toBe(['a', 'b'])
        ->and($model->is_active)->toBeTrue()
        ->and($model->score)->toBe(42.75)
        ->and($model->visits)->toBe(7)
        ->and($model->birthday)->toBeInstanceOf(DateTime::class)
        ->and($model->birthday->format('Y-m-d'))->toBe('2024-02-01')
        ->and($model->status)->toBe(AttributeCastingStatus::Live)
        ->and($model->secret_payload)->toBe(['token' => 'abc']);
});

it('caches custom cast classes and attribute objects when enabled', function () {
    $model = new AttributeCastingModel();
    $model->code = 'abc';

    $first = $model->code;
    $second = $model->code;

    $cachedFirst = $model->cached_label;
    $cachedSecond = $model->cached_label;

    $uncachedFirst = $model->uncached_label;
    $uncachedSecond = $model->uncached_label;

    expect($first)->toBe($second)
        ->and($first->value)->toBe('ABC')
        ->and(UppercaseCast::$getCalls)->toBe(1)
        ->and($cachedFirst)->toBe($cachedSecond)
        ->and($uncachedFirst)->not->toBe($uncachedSecond);
});

it('applies casts accessors appends and hidden attributes to arrays', function () {
    $model = new AttributeCastingModel();
    $model->fill([
        'first_name' => 'jane',
        'last_name' => 'doe',
        'settings' => ['theme' => 'light'],
        'options' => ['x'],
        'status' => AttributeCastingStatus::Draft,
        'secret_payload' => ['token' => 'hidden'],
    ]);

    $array = $model->toArray();

    expect($array['first_name'])->toBe('Jane')
        ->and($array['last_name'])->toBe('DOE')
        ->and($array['settings'])->toBe(['theme' => 'light'])
        ->and($array['options'])->toBe(['x'])
        ->and($array['status'])->toBe('draft')
        ->and($array['full_name'])->toBe('Jane DOE')
        ->and($array)->not->toHaveKey('secret_payload');
});
