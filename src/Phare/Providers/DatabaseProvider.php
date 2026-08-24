<?php

namespace Phare\Providers;

use Phare\Database\Connection;
use Phare\Database\MySql\DatabaseManager;
use Phare\Support\ServiceProvider;

class DatabaseProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('dbManager', function () use ($app) {
            foreach ($app['config']->path('app.phalcon.db') ?? [] as $key => $value) {
                ini_set("phalcon.db.$key", $value);
            }

            $connections = $app['config']->path('database.connections')?->toArray();

            return (new DatabaseManager($app, $connections))
                ->setupDatabases();
        });

        // 'db.manager' is the canonical Laravel-style alias for the manager so
        // contextual attributes (e.g. #[DB('reports')]) and other consumers can
        // ask for connection(name) directly.
        $app->singleton('db.manager', function () use ($app) {
            return $app->make('dbManager');
        });

        $app->singleton('db', function () use ($app) {
            $manager = $app->make('dbManager');

            return $manager->connection();
        });

        // Phare-typed seam over the Phalcon adapter. Prefer this over 'db' in
        // Phare code; 'db' stays a raw Phalcon adapter because Phalcon's own ORM
        // resolves it out of the DI and requires the native type.
        $app->singleton('db.connection', function () use ($app) {
            return Connection::wrap($app->make('db'));
        });

        $app->singleton(Connection::class, function () use ($app) {
            return $app->make('db.connection');
        });
    }
}
