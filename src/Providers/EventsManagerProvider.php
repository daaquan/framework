<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Events\Manager as EventsManager;
use Phare\Foundation\AbstractApplication as Application;

class EventsManagerProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $di): void
    {
        $di->singleton('eventsManager', function () {
            $manager = new EventsManager();
            $manager->enablePriorities(true);

            return $manager;
        });
    }
}
