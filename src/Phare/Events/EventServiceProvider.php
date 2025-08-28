<?php

namespace Phare\Events;

use Phare\Support\ServiceProvider;
use Phare\Events\Contracts\Dispatcher as DispatcherContract;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('events', function ($app) {
            return new Dispatcher($app);
        });

        $this->app->bind(DispatcherContract::class, function ($app) {
            return $app['events'];
        });
    }

    public function boot(): void
    {
        // Register event listeners and subscribers
        $this->registerEventListeners();
        $this->registerEventSubscribers();
    }

    protected function registerEventListeners(): void
    {
        $listeners = $this->listens();

        foreach ($listeners as $event => $eventListeners) {
            foreach ($eventListeners as $listener) {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    protected function registerEventSubscribers(): void
    {
        $subscribers = $this->subscribe();

        foreach ($subscribers as $subscriber) {
            $this->app['events']->subscribe($subscriber);
        }
    }

    /**
     * The event listener mappings for the application.
     */
    protected function listens(): array
    {
        return [];
    }

    /**
     * The subscriber classes to register.
     */
    protected function subscribe(): array
    {
        return [];
    }
}