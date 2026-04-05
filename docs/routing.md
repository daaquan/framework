# Routing

Routes are registered through `Phare\Routing\Router`.

## Basic routes

```php
use Phare\Routing\Router;

/** @var Router $router */

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->patch('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
$router->options('/users', [UserController::class, 'options']);
```

Handlers can be a `[Controller::class, 'method']` array or a closure:

```php
$router->get('/ping', function () {
    return response()->json(['status' => 'ok']);
});
```

## Named routes

```php
$router->get('/users/{id}', [UserController::class, 'show']);
$router->name('users.show');
```

## Middleware

Pass middleware as the third argument:

```php
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
```

## Route groups

```php
$router->group(['prefix' => '/api/v1', 'middleware' => ['auth', 'throttle']], function (Router $router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);
});
```

## Resource routes

Registers the standard RESTful set of routes for a controller:

```php
$router->resource('/posts', PostController::class);
```

Generates:

| Method | URI | Action |
|--------|-----|--------|
| GET | `/posts` | `index` |
| GET | `/posts/create` | `create` |
| POST | `/posts` | `store` |
| GET | `/posts/{id}` | `show` |
| GET | `/posts/{id}/edit` | `edit` |
| PUT | `/posts/{id}` | `update` |
| DELETE | `/posts/{id}` | `destroy` |

## Route parameters

Parameters are defined with `{name}` syntax. Inline regex constraints use `{name:[pattern]}`:

```php
$router->get('/users/{id:[0-9]+}', [UserController::class, 'show']);
```

Parameters are injected into controller actions by position. The resolved `Request` object and
validated `FormRequest` subclasses are also auto-injected.

## Route files

By convention, route definitions live in `routes/web.php` (or `routes/api.php`) and are loaded
by `Phare\Routing\FileRouteLoader`.

```php
// routes/web.php
/** @var Phare\Routing\Router $router */

$router->get('/', [HomeController::class, 'index']);
```

## Route caching

Cache routes for production:

```bash
php artisan route:cache
```

The kernel checks `$app->routesIsCached()` and loads the compiled cache when present.
