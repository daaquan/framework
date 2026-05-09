<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Database\MySql\DatabaseManager;
use Phare\Foundation\AbstractApplication as Application;

class DatabaseProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
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
    }
}
