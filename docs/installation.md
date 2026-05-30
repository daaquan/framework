# Installation

## Requirements

- PHP 8.3+
- `ext-phalcon ^5.9.2`

## Composer

```bash
composer require phare/framework
```

## Docker (recommended for development)

The repository ships with a Docker setup that compiles the Phalcon extension for you.

```bash
# Build image and drop into the container shell
docker compose run --build app
```

Inside the container you can run the full test suite or interact with the framework
without installing the Phalcon extension locally.

## Application bootstrap

Create a bootstrap file (e.g. `bootstrap/app.php`) and return the application instance:

```php
<?php

use Phare\Foundation\Web;

$app = new Web(__DIR__ . '/..');

$app->bootstrapWith([
    \Phare\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Phare\Foundation\Bootstrap\LoadConfiguration::class,
    \Phare\Foundation\Bootstrap\RegisterProviders::class,
    \Phare\Foundation\Bootstrap\BootProviders::class,
]);

return $app;
```

## Environment file

Copy `.env.example` to `.env` and edit the values:

```
APP_ENV=local
APP_KEY=

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

## Running tests

```bash
# Full suite (suppresses PHP deprecation warnings)
bin/pest

# Single file
bin/pest tests/Eloquent/EloquentBuilderTest.php

# Filtered by name
bin/pest --filter="test name"
```

## Code style and static analysis

```bash
# Auto-fix code style (Laravel Pint preset)
vendor/bin/pint

# Static analysis (PHPStan level 8)
vendor/bin/phpstan analyse
```
