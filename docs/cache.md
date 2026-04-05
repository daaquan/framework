# Cache

`Phare\Cache\CacheManager` implements PSR-16 (`SimpleCacheInterface`) and supports
multiple storage backends.

## Configuration

`config/cache.php`:

```php
return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver'       => 'file',
            'path'         => storage_path('framework/cache'),
            'defaultSerializer' => 'Php',
        ],
        'redis' => [
            'driver' => 'redis',
            'host'   => env('REDIS_HOST', '127.0.0.1'),
            'port'   => env('REDIS_PORT', 6379),
            'auth'   => env('REDIS_PASSWORD'),
            'index'  => env('REDIS_DB', 0),
        ],
        'apcu' => [
            'driver' => 'apcu',
        ],
        'array' => [
            'driver' => 'array',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
```

## Basic usage

Use the `cache()` helper or the `Cache` facade:

```php
// Store a value (TTL in seconds, or null for forever)
cache()->set('user:42', $user, ttl: 3600);

// Retrieve (returns $default when key is absent)
$user = cache()->get('user:42', null);

// Delete
cache()->delete('user:42');

// Clear the entire cache store
cache()->clear();
```

## Supported drivers

| Driver key | Backend | Notes |
|---|---|---|
| `file` / `stream` | Filesystem | Default; suitable for single-server setups |
| `redis` | Redis | Requires `ext-redis` |
| `apc` / `apcu` | APCu in-memory | Requires `ext-apcu` |
| `array` | PHP array | Per-request only; useful in tests |
| `null` | Discard all writes | Useful in tests or to disable caching |

## Switching stores at runtime

```php
app(\Phare\Cache\CacheManager::class)->adapter(); // returns the active adapter
```

To use a non-default store, resolve a dedicated `CacheManager` instance bound to
the desired driver via the service container.

## Testing

Use the `array` driver in `config/cache.php` (or override via `.env`) so tests run
without external dependencies:

```
CACHE_DRIVER=array
```
