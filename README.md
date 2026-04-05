# Phare Framework

Phare is a lightweight PHP framework built on the [Phalcon](https://phalcon.io/) C extension.
It wraps Phalcon's low-level APIs with Laravel-like conventions: service container,
Eloquent-style ORM, middleware pipeline, console commands, and helper functions.

**Requirements:** PHP 8.2+ · `ext-phalcon ^5.9.2`

## Features

- **Service container** — binding, singletons, contextual bindings, tags, resolving callbacks
- **Eloquent-style ORM** — models, query builder, relations, eager loading, soft deletes, global/local scopes
- **HTTP kernel** — middleware pipeline, Request/Response wrappers, form request validation
- **Routing** — fluent route registration, groups, resource routes, route caching
- **Console commands** — Artisan-like commands with rich input/output helpers
- **Database** — schema builder, migrations, seeders, model factories
- **Authentication** — session-based auth with guards and events
- **Cache** — file, Redis, APCu, and array drivers (PSR-16)
- **Helper functions** — path helpers, `app()`, `auth()`, `cache()`, `response()`, and more

## Installation

```bash
composer require phare/framework
```

## Documentation

Full documentation is in the [`docs/`](docs/index.md) directory:

- [Installation & setup](docs/installation.md)
- [Service container](docs/container.md)
- [Routing](docs/routing.md)
- [HTTP layer](docs/http.md)
- [Eloquent ORM](docs/eloquent.md)
- [Database & migrations](docs/database.md)
- [Console commands](docs/console.md)
- [Authentication](docs/auth.md)
- [Cache](docs/cache.md)

## Docker

The repository ships with a Docker setup that compiles the Phalcon extension automatically.
After installing [Docker](https://www.docker.com/):

```bash
docker compose run --build app
```

This drops you into a container with all PHP extensions available, ready to run the
framework or its test suite.

## Testing

```bash
# Full suite (suppresses PHP deprecation warnings)
bin/pest

# Single file
bin/pest tests/Eloquent/EloquentBuilderTest.php

# Filtered by test name
bin/pest --filter="test name"
```

## Code style and static analysis

```bash
# Auto-fix code style (Laravel Pint preset)
vendor/bin/pint

# Static analysis (PHPStan level 8)
vendor/bin/phpstan analyse
```

## License

This project is open-sourced under the [MIT license](LICENSE).
