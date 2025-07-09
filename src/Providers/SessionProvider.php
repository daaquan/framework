<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Session\SessionManager;
use Phare\Storage\Adapter\RedisCluster;

class SessionProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('session', function () {
            switch (config('session.driver')) {
                case 'file':
                    $options = ['savePath' => config('session.files')];
                    $adapter = new \Phalcon\Session\Adapter\Stream($options);
                    break;
                case 'redis':
                    $config = config('database.connections.redis.session');
                    if ($config->cluster) {
                        $adapter = new \Phare\Session\Adapter\RedisCluster(
                            new RedisCluster(new SerializerFactory(),
                                $config->path('default')->toArray())
                        );
                    } else {
                        $adapter = new \Phalcon\Session\Adapter\Redis(
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
