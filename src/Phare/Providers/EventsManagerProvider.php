<?php

namespace Phare\Providers;

use Phalcon\Events\Manager as EventsManager;
use Phare\Support\ServiceProvider;

class EventsManagerProvider extends ServiceProvider
{
    public function register(): void
    {
        $di = $this->app;
        $di->singleton('eventsManager', function () {
            $manager = new EventsManager();
            $manager->enablePriorities(true);

            return $manager;
        });
    }
}
