# OpenWolf

@.wolf/OPENWOLF.md

This project uses OpenWolf for context management. Read and follow .wolf/OPENWOLF.md every session. Check .wolf/cerebrum.md before generating code. Check .wolf/anatomy.md before reading files.


# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phare is a PHP framework built on the Phalcon C extension (`ext-phalcon ^5.9.2`). It wraps Phalcon's low-level APIs with Laravel-like conventions: service container, Eloquent-style ORM, middleware pipeline, console commands, and helper functions. Requires PHP 8.2+.

## Commands

```bash
# Run tests (suppresses PHP deprecation warnings)
bin/pest

# Run a single test file
bin/pest tests/Eloquent/EloquentBuilderTest.php

# Run a filtered test
bin/pest --filter="test name"

# Lint / auto-fix code style (Laravel preset)
vendor/bin/pint

# Static analysis (level 8)
vendor/bin/phpstan analyse

# Docker dev environment (builds Phalcon extension)
docker compose run --build app
```

## Architecture

**Namespace root:** `Phare\` → `src/Phare/`

### Core layers

- **Foundation** — `AbstractApplication` bootstraps the container and registers providers. `Web` and `Micro` are concrete app types. `Http\Kernel` handles the middleware/request pipeline.
- **Container** — Service container with binding/resolution, used throughout.
- **Routing** — `Router` wraps Phalcon's router. `RouteLoader` and its subclasses (`FileRouteLoader`, `ControllerRouteLoader`) register routes. Middleware sits in `Routing\Middleware\`.
- **Eloquent** — Laravel-inspired ORM layer over Phalcon's Models. `Model` is the base, `Builder`/`BuilderInterface` provide query building. `Concerns\` holds model traits.
- **Database** — Schema builder, blueprints, migrations, seeders, and factories — all wrapping Phalcon's DB layer with a migration system.

### Supporting modules

Auth, Cache, Config, Console, Encryption, Events, Hashing, Http, Mail, Middleware (CSRF, etc.), Queue (Beanstalk via Pheanstalk), RateLimit, Session, Storage, Validation, View, Translation.

### Test setup

- **Pest 2** with `tests/Pest.php` configuring base test cases.
- Tests in `Database/` and `Eloquent/` directories use `Tests\TestCase` which boots a full application from `tests/Mock/bootstrap/app.php`.
- SQLite in-memory for database tests (`DB_DATABASE=db`, `DB_CONNECTION=sqlite`).
- Mockery for mocking.

## Style

- Code style: Laravel Pint preset (`pint.json`). Run `vendor/bin/pint` before committing.
- PHPStan level 8 for static analysis.
- PSR-4 autoloading.
