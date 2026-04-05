# Authentication

Phare ships with session-based authentication via `Phare\Auth\Manager`.

## Configuration

`config/auth.php`:

```php
return [
    'model' => \App\Models\User::class,

    'password_field' => 'password',   // column checked during attempt()
];
```

The `User` model must implement `Phare\Auth\Contracts\User` (or extend
`Phare\Auth\User`).

## Basic usage

The `auth()` helper and `Auth` facade both proxy to the `Manager` singleton.

```php
// Check authentication state
auth()->check();    // bool — is a user logged in?
auth()->guest();    // bool — opposite of check()
auth()->id();       // int|string|null — current user's primary key
auth()->user();     // User model instance or null
```

### Logging in

```php
// Attempt with credentials (checks password hash automatically)
$success = auth()->attempt([
    'email'    => $request->input('email'),
    'password' => $request->input('password'),
]);

if (! $success) {
    return back()->withErrors(['email' => 'Invalid credentials.']);
}

return redirect('/dashboard');
```

### Direct login (no password check)

```php
auth()->login($user);           // log in a User model instance
auth()->loginUsingId(42);       // log in by primary key
```

### Logging out

```php
auth()->logout();
```

### Validate without logging in

```php
$valid = auth()->validate(['email' => $email, 'password' => $password]);
```

## Auth middleware

Protect routes by applying the `auth` middleware alias:

```php
// Single route
$router->get('/profile', [ProfileController::class, 'show'], ['auth']);

// Route group
$router->group(['middleware' => ['auth']], function (Router $router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/settings', [SettingsController::class, 'index']);
});
```

The built-in `Phare\Auth\Middleware\Authenticate` redirects unauthenticated users
to `/login` by default. Override `redirectTo()` to customize:

```php
class Authenticate extends \Phare\Auth\Middleware\Authenticate
{
    protected function redirectTo(Request $request): string
    {
        return $request->wantsJson() ? '' : '/login';
    }
}
```

## Events

The following events are dispatched during the authentication lifecycle:

| Event class | Fired when |
|---|---|
| `Phare\Auth\Events\Attempting` | Before credentials are checked |
| `Phare\Auth\Events\Authenticated` | User successfully authenticated |
| `Phare\Auth\Events\Login` | User logged in (session started) |
| `Phare\Auth\Events\Failed` | Authentication attempt failed |
| `Phare\Auth\Events\Logout` | User logged out |

Listen for events in a provider:

```php
Event::listen(\Phare\Auth\Events\Login::class, function ($event) {
    logger()->info('User logged in', ['id' => $event->user->id]);
});
```
