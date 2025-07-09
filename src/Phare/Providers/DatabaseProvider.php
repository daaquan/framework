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
    }
}
