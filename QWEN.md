# Phare Framework Documentation

## Overview

Phare is a lightweight PHP framework built on top of the [Phalcon](https://phalcon.io/) extension. It provides a set of components for routing, database access, command line tooling, and more. The framework follows modern PHP practices and provides a clean, expressive API for building web applications.

## Key Features

- Service container with dependency injection
- Eloquent-style ORM based on Phalcon's Model
- Console command support with Symfony Console integration
- HTTP kernel with middleware support
- Routing system with RESTful resource routing
- Collection implementation with extensive array manipulation methods
- Helper functions including various path helpers
- Configuration management with environment variable support

## Architecture

### Core Components

1. **Container**: The service container (`Phare\Container\Container`) extends Phalcon's DI and provides dependency injection capabilities with support for singletons, bindings, and autowiring.

2. **Application**: The base application class (`Phare\Foundation\AbstractApplication`) provides the foundation for web and console applications, including configuration loading, service provider registration, and bootstrapping.

3. **Routing**: The routing system (`Phare\Routing\Router`) provides a clean API for defining routes with HTTP verbs, route parameters, middleware, and named routes.

4. **HTTP Layer**: Request (`Phare\Http\Request`) and Response (`Phare\Http\Response`) classes provide a convenient interface for handling HTTP interactions with validation capabilities.

5. **Database**: The Eloquent implementation (`Phare\Eloquent\Model`) extends Phalcon's Model with Laravel-like features such as mass assignment, attribute casting, and query building.

6. **Collections**: The Collection class (`Phare\Collections\Collection`) provides a fluent interface for working with arrays of data with methods for filtering, mapping, grouping, and more.

7. **Console**: The console component (`Phare\Console`) integrates Symfony Console for creating command-line tools with a Laravel-like API.

### Directory Structure

```
src/Phare/
├── Attributes/        # PHP 8 attributes for routing and other features
├── Auth/              # Authentication system
├── Bootstrap/         # Bootstrap files for environment loading
├── Cache/             # Cache management
├── Collections/       # Collection and string utilities
├── Console/           # Console command system
├── Container/         # Service container implementation
├── Contracts/         # Interfaces for core components
├── Database/          # Database connection management
├── Debug/             # Debugging utilities
├── Eloquent/          # ORM implementation
├── Exceptions/        # Custom exceptions
├── Foundation/        # Core application foundation
├── Http/              # HTTP request/response handling
├── Log/               # Logging system
├── Providers/         # Service providers
├── Routing/           # Routing system
├── Session/           # Session management
├── Storage/           # Storage adapters
├── Support/           # Helper functions and utilities
├── Testing/           # Testing utilities
└── View/              # View rendering (Blade support)
```

## Key Concepts

### Service Container

The service container is the backbone of the framework, managing class dependencies and performing dependency injection. It supports:

- Automatic resolution of class dependencies
- Binding interfaces to implementations
- Singleton instances
- Aliasing services

### Routing

Routes are defined using a fluent API that supports:

- HTTP verb constraints (GET, POST, PUT, DELETE, etc.)
- Route parameters with constraints
- Middleware attachment
- Named routes for URL generation
- Resource routing for RESTful controllers

### Eloquent ORM

The Eloquent implementation provides:

- Model classes with mass assignment protection
- Attribute casting (int, string, boolean, date, etc.)
- Query building with method chaining
- Relationship definitions
- Soft deletes support

### Collections

Collections provide a powerful way to work with arrays:

- Fluent interface for data manipulation
- Filtering, mapping, grouping operations
- Statistical methods (sum, average, min, max)
- Sorting and pagination helpers

### Console

The console component allows creating CLI commands with:

- Argument and option definitions
- Interactive prompts (confirmation, questions, choices)
- Output formatting
- Command scheduling capabilities

## Helper Functions

The framework provides a set of global helper functions for common tasks:

- `app()` - Access the application instance or resolve services
- `config()` - Access configuration values
- `env()` - Get environment variables
- `request()` - Access the current request
- `response()` - Create HTTP responses
- `view()` - Render views
- `route()` - Generate URLs for named routes
- `collect()` - Create collection instances
- Path helpers (`base_path()`, `storage_path()`, etc.)

## Installation

```bash
composer require phare/framework
```

## Docker Support

The framework includes Docker configuration for easy setup:

```bash
docker compose run --build app
```

## Testing

Run the test suite using Pest:

```bash
bin/pest
```

## Requirements

- PHP 8.2+
- Phalcon extension 5.9.2+
- Various PHP extensions (curl, json, mbstring, openssl, intl, pdo, gmp, bcmath, zip, zlib)

## License

This project is open-sourced under the MIT license.