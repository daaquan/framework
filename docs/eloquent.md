# Eloquent ORM

Phare provides a Laravel Eloquent-compatible ORM layer built on top of Phalcon models.

## Defining a model

```php
use Phare\Eloquent\Model;

class User extends Model
{
    protected string $table = 'users';   // defaults to snake_case plural of class name
    protected string $primaryKey = 'id';

    // Attributes that should be hashed automatically
    protected array $passwordAttributes = ['password'];
}
```

## Querying

### Retrieving records

```php
// All rows
$users = User::all();

// Query builder
$active = User::where('status', 'active')->get();

// Single record
$user = User::first(1);          // by primary key
$user = User::firstOrFail(42);   // throws ModelNotFoundException

// Fluent conditions
$admins = User::where('role', 'admin')
              ->where('active', true)
              ->orderBy('name')
              ->limit(10)
              ->get();
```

### Query builder methods

| Method | Description |
|--------|-------------|
| `where($field, $op, $value)` | AND WHERE condition |
| `orWhere($field, $op, $value)` | OR WHERE condition |
| `whereIn($field, $values)` | WHERE IN |
| `whereNotIn($field, $values)` | WHERE NOT IN |
| `whereBetween($field, $min, $max)` | WHERE BETWEEN |
| `whereNull($field)` | WHERE IS NULL |
| `whereNotNull($field)` | WHERE IS NOT NULL |
| `whereLike($field, $value)` | WHERE LIKE |
| `whereRaw($cond, $bind)` | Raw condition |
| `orderBy($col, $dir)` | ORDER BY |
| `limit($n, $offset)` | LIMIT / OFFSET |
| `paginate($page, $limit)` | Paginate |
| `columns($cols)` | SELECT columns |
| `groupBy($col)` | GROUP BY |
| `get()` | Execute and return collection |
| `first()` | First result or null |
| `last()` | Last result or null |

### Updating and deleting via builder

```php
User::where('inactive', true)->update(['status' => 'archived']);
User::where('created_at', '<', '2020-01-01')->delete();
```

## Creating and saving

```php
// Create new record
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$user->save();

// Fill from array
$user = new User();
$user->fill(['name' => 'Bob', 'email' => 'bob@example.com'])->save();

// Or shorthand
$user = new User();
$user->create(['name' => 'Carol', 'email' => 'carol@example.com']);
```

## Relations

### HasOne / HasMany

```php
class User extends Model
{
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

### BelongsTo

```php
class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### MorphOne / MorphMany / MorphTo

```php
class Post extends Model
{
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

class Image extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

### Through relations

```php
class Country extends Model
{
    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class);
    }
}
```

## Eager loading

```php
$users = User::query()->with('posts', 'profile')->get();

// Nested eager loading
$users = User::query()->with(['posts' => fn ($q) => $q->where('published', true)])->get();
```

## Soft deletes

Add the `SoftDeletes` trait to your model:

```php
use Phare\Eloquent\Concerns\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
}
```

```php
$post->delete();                        // sets deleted_at
Post::withTrashed()->get();             // include soft-deleted
Post::onlyTrashed()->get();             // only soft-deleted
Post::withoutTrashed()->get();          // exclude soft-deleted (default)
$post->restore();                       // restore a soft-deleted record
$post->forceDelete();                   // permanently delete
```

## Global scopes

```php
use Phare\Eloquent\Scope;
use Phare\Eloquent\Builder;

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('active', true);
    }
}

class User extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveScope());
    }
}
```

Remove a scope for a specific query:

```php
User::query()->withoutGlobalScope(ActiveScope::class)->get();
User::query()->withoutGlobalScopes()->get(); // remove all
```

## Local scopes

```php
class User extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }
}

// Usage
User::query()->active()->role('admin')->get();
```

## Morph map

Register morph aliases to keep class names out of the database:

```php
use Phare\Eloquent\Model;

Model::morphMap([
    'post'  => Post::class,
    'video' => Video::class,
]);
```

## Serialization

```php
$user->toArray();
```
