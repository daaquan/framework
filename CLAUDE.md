# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
- Run tests: `bin/pest` (wrapper script that hides PHP deprecation warnings)
- Run PHPUnit directly: `./vendor/bin/phpunit`
- Run PHPStan (static analysis): `./vendor/bin/phpstan analyse`
- Run specific test file: `bin/pest tests/Unit/ExampleTest.php`
- Run tests with coverage: `bin/pest --coverage`

### Code Formatting
- Format PHP code: `./vendor/bin/pint`
- Check code style: `./vendor/bin/pint --test`
- Configuration in `pint.json` (Laravel preset with custom rules)

### Dependencies
- Install dependencies: `composer install`
- Framework requires PHP 8.2+ and Phalcon extension 5.9.2+

### Docker Development
- Build and run container: `docker compose run --build app`
- Container includes PHP extensions and Phalcon setup

## Architecture Overview

### Framework Structure
Phare is a lightweight PHP framework built on Phalcon, providing Laravel-like features with Phalcon performance.

### Core Components

#### Application Types
- **Web Application** (`Phare\Foundation\Web`): Full MVC application using Phalcon Application
- **Micro Application** (`Phare\Foundation\Micro`): Lightweight API/microservice using Phalcon Micro
- Both extend `AbstractApplication` which provides common functionality

#### Dependency Injection
- Custom container (`Phare\Container\Container`) extends Phalcon DI
- Supports Laravel-style binding: `bind()`, `singleton()`, `make()`
- Includes autowiring with reflection-based dependency resolution
- Reserved services map to Phalcon standard services

#### Key Directories
- `src/Phare/Foundation/`: Application bootstrapping and base classes
- `src/Phare/Container/`: Dependency injection container
- `src/Phare/Routing/`: Route handling and middleware
- `src/Phare/Console/`: Artisan-like command system
- `src/Phare/Eloquent/`: ORM layer (Eloquent-style)
- `src/Phare/Support/`: Helper functions and utilities
- `tests/`: PHPUnit/Pest test suite with mock application

#### Bootstrap Process
Applications bootstrap through:
1. Load environment variables
2. Load configuration files (cached or individual)
3. Register service providers
4. Register facades/aliases
5. Handle exceptions

#### Configuration
- Configuration files in `config/` directory
- Supports configuration caching in `bootstrap/cache/config.php`
- Environment-based configuration with `.env` support

#### Service Providers
Service providers in `src/Phare/Providers/` register framework components:
- `AuthServiceProvider`, `CacheProvider`, `DatabaseProvider`
- `RouteServiceProvider`, `ViewProvider`, etc.

#### Testing Setup
- Uses Pest testing framework
- Mock application in `tests/Mock/` with complete app structure
- Test environment configured in `phpunit.xml.dist`
- SQLite database for testing

## Development Notes

### Path Helpers
The framework provides path helper methods on the application instance:
- `basePath()`, `configPath()`, `databasePath()`
- `storagePath()`, `resourcePath()`, `bootstrapPath()`

### Environment Detection
- `environment()` method checks APP_ENV
- `runningInConsole()` detects CLI usage
- `runningUnitTests()` detects test environment

### Route Caching
- Routes can be cached in `bootstrap/cache/routes.php`
- Check with `routesIsCached()` method

### Container Features
- Laravel-style service binding with `bind()` and `singleton()`
- Autowiring support through reflection
- Facade system for service access
- Reserved services automatically map to Phalcon standard services

### Application Lifecycle
- Applications extend `AbstractApplication` (base container + application contract)
- Web apps use `Phalcon\Mvc\Application`, Micro apps use `Phalcon\Mvc\Micro`
- Bootstrap sequence: env → config → providers → facades → exception handling
- Service providers register framework components during bootstrap

### Testing Structure
- Mock application structure in `tests/Mock/` mirrors real application layout
- Test environment uses SQLite database and array cache driver
- PHPUnit configuration excludes deprecated warnings via `bin/pest` wrapper