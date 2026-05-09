# Phare Framework — Documentation

Phare is a lightweight PHP framework built on the [Phalcon](https://phalcon.io/) C extension.
It wraps Phalcon's low-level APIs with Laravel-like conventions: service container,
Eloquent-style ORM, middleware pipeline, console commands, and helper functions.

**Requirements:** PHP 8.2+, `ext-phalcon ^5.9.2`

## Contents

| Guide | Description |
|---|---|
| [Installation](installation.md) | Composer setup, Docker dev environment, environment configuration |
| [Service Container](container.md) | Binding, resolution, contextual bindings, tags |
| [Routing](routing.md) | Route registration, groups, resources, middleware |
| [HTTP Layer](http.md) | Request, Response, middleware pipeline |
| [Eloquent ORM](eloquent.md) | Models, query builder, relations, soft deletes, scopes |
| [Database](database.md) | Migrations, schema builder, seeders, factories |
| [Console](console.md) | Artisan-style commands, input/output helpers |
| [Authentication](auth.md) | Session-based auth, guards, login/logout |
| [Cache](cache.md) | File, Redis, APCu, and array cache drivers |

## Architecture

For implementation and compatibility details see
[laravel13-phalcon-architecture.md](laravel13-phalcon-architecture.md).
