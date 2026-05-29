<?php

namespace Phare\Providers;

use Phalcon\Session\Adapter\Redis;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phare\Session\SessionManager;
use Phare\Session\SessionStoreManager;
use Phare\Storage\Adapter\RedisCluster;
use Phare\Support\ServiceProvider;

class SessionProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('session.manager', function ($app) {
            return new SessionStoreManager($app);
        });

        $app->singleton('session', function () {
            switch (config('session.driver')) {
                case 'file':
                    $options = ['savePath' => config('session.files')];
                    $adapter = new Stream($options);
                    break;
                case 'redis':
                    $config = config('database.connections.redis.session');
                    if ($config->cluster) {
                        $adapter = new \Phare\Session\Adapter\RedisCluster(
                            new RedisCluster(new SerializerFactory(),
                                $config->path('default')->toArray())
                        );
                    } else {
                        $adapter = new Redis(
                            new AdapterFactory(new SerializerFactory()),
                            $config->path('default')->toArray()
                        );
                    }
                    break;
                default:
                    throw new \Exception('Invalid session driver.');
            }

            $session = (new SessionManager())
                ->setAdapter($adapter);
            $session->start();

            return $session;
        });
    }
}
