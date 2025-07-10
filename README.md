# Phare Framework

Phare is a lightweight PHP framework built on top of the [Phalcon](https://phalcon.io/) extension. It provides a set of components for routing, database access, command line tooling and more.

## Features

- Service container
- Eloquent style ORM
- Console command support
- HTTP kernel with middleware
- Helper functions including various path helpers

## Installation

```
composer require phare/framework
```

## Docker

The repository provides a simple Docker setup. After installing
[Docker](https://www.docker.com/) run the following command to build and
start a container:

```
docker compose run --build app
```

This will compile the necessary PHP extensions, including the Phalcon
extension, and drop you into a container where you can run the framework
or its test suite.

## License

This project is open-sourced under the MIT license.
