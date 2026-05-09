# HTTP Layer

## Request

`Phare\Http\Request` extends Phalcon's HTTP request with a Laravel-like API.

### Accessing input

```php
// All input (POST body + query string)
$all = $request->all();

// Specific keys
$name  = $request->input('name', 'default');
$page  = $request->query('page', 1);

// Subset of keys
$data = $request->only(['name', 'email']);
$data = $request->except(['_token']);

// Existence checks
$request->has('email');         // true if key present (even empty)
$request->filled('email');      // true if key present and non-empty
$request->missing('email');     // true if key absent
```

### Headers and meta

```php
$request->header('Accept');
$request->headers();            // all headers as array
$request->bearerToken();        // value from "Authorization: Bearer <token>"
$request->ip();
$request->url();
$request->fullUrl();            // includes query string
$request->isMethod('POST');
$request->isJson();
$request->wantsJson();
```

### Route parameters

```php
$request->route();          // full route info array
$request->route('id');      // single route parameter
```

### Validation

Define rules in the constructor or use `make()`:

```php
$request = Request::make(
    data: $request->all(),
    rules: ['name' => 'required|string', 'email' => 'required|email'],
);

if (! $request->validate($request->all())) {
    $errors = $request->getMessages();
}
```

For form request objects, extend `Request` and define `rules()`:

```php
class StoreUserRequest extends \Phare\Http\Request
{
    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];
    }
}
```

The router auto-validates and injects `FormRequest` parameters when they appear in a
controller action's signature.

## Response

`Phare\Http\Response` extends Phalcon's HTTP response.

### JSON

```php
return response()->json(['user' => $user], 201);
// or
$response->json(['error' => 'Not found'], 404);
```

### Status and headers

```php
$response->status(204);
$response->header('X-Request-Id', $id);
$response->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
```

### Views

```php
return response()->view('users.index', ['users' => $users]);
```

### Redirects

```php
return response()->redirect('/dashboard');
return response()->redirectTo('/login', 302);
return response()->back();           // redirect to referrer
```

### Cookies

```php
$response->cookie('remember_token', $token, ttl: 60 * 24 * 30);
```

## Middleware

Middleware classes implement `Phare\Middleware\MiddlewareInterface`:

```php
use Phare\Http\Request;
use Phare\Http\Response;

class AuthMiddleware implements \Phare\Middleware\MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (! auth()->check()) {
            return response()->redirectTo('/login');
        }

        return $next($request);
    }
}
```

### Registering middleware

In your `Http\Kernel` subclass:

```php
protected array $middleware = [
    \Phare\Middleware\TrimStrings::class,
];

protected array $middlewareGroups = [
    'web' => [
        \Phare\Session\StartSession::class,
        \Phare\Middleware\VerifyCsrfToken::class,
    ],
    'api' => [
        \Phare\Middleware\ThrottleRequests::class,
    ],
];

protected array $routeMiddleware = [
    'auth'     => \Phare\Auth\Middleware\Authenticate::class,
    'throttle' => \Phare\Middleware\ThrottleRequests::class,
];
```
