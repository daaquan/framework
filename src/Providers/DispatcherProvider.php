<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Events\Event;
use Phalcon\Events\Manager;
use Phalcon\Mvc\Dispatcher;
use Phare\Foundation\AbstractApplication as Application;

class DispatcherProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('eventsManager', function () use ($app) {
            $eventsManager = new Manager();
            $eventsManager->attach('dispatch:beforeException',
                function (Event $event, Dispatcher $dispatcher, \Throwable $exception) use ($app) {
                    if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {
                        $dispatcher->setReturnedValue('');

                        $app['response']
                            ->setStatusCode(404)
                            ->send();

                        return false;
                    }

                    return true;
                });

            return $eventsManager;
        });

        $app->singleton('dispatcher', function () use ($app) {
            $dispatcher = new Dispatcher();
            $dispatcher->setActionSuffix('');
            $dispatcher->setEventsManager($app['eventsManager']);

            return $dispatcher;
        });
    }
}
