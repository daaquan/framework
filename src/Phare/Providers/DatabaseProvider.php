<?php

namespace Phare\Providers;

use Phalcon\Config\Config;
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

        // Eagerly setup database connections so 'db' and other connection names are available
        $app->singleton('db', function () use ($app) {
            // Trigger dbManager initialization which registers all connection singletons
            $app->make('dbManager');

            // Now 'db' should be re-bound by setupDatabases, resolve it
            $default = $app['config']->path('database.default', 'db');
            $connections = $app['config']->path('database.connections');
            $connConfig = $connections?->path($default);
            if (!$connConfig) {
                throw new \RuntimeException("Database connection '{$default}' is not configured.");
            }

            $connConfig = $connConfig instanceof Config ? $connConfig->toArray() : (array)$connConfig;

            return (new DatabaseManager($app, [$default => $connConfig]))->getConnection($connConfig);
        });
    }
}
