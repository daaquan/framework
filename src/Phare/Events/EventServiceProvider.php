<?php

namespace Phare\Events;

use Phare\Events\Contracts\Dispatcher as DispatcherContract;
use Phare\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string|array|\Closure>>
     */
    protected array $listen = [];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, string|object>
     */
    protected array $subscribe = [];

    public function register(): void
    {
        $container = $this->app;

        $this->app->singleton('events', function () use ($container) {
            $dispatcher = new Dispatcher($container);
            $dispatcher->setTransactionManagerResolver(function () use ($container) {
                if (method_exists($container, 'bound') && $container->bound('dbManager')) {
                    return $container->make('dbManager');
                }
            });

            return $dispatcher;
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
        $listeners = $this->getEvents();

        foreach ($listeners as $event => $eventListeners) {
            foreach (array_unique($eventListeners, SORT_REGULAR) as $listener) {
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
        return $this->listen;
    }

    /**
     * The subscriber classes to register.
     */
    protected function subscribe(): array
    {
        return $this->subscribe;
    }

    protected function getEvents(): array
    {
        if (method_exists($this->app, 'eventsAreCached')
            && method_exists($this->app, 'getCachedEventsPath')
            && $this->app->eventsAreCached()) {
            $cache = require $this->app->getCachedEventsPath();

            return $cache[static::class] ?? [];
        }

        return array_merge_recursive(
            $this->discoveredEvents(),
            $this->listens()
        );
    }

    /**
     * Get the discovered events and listeners.
     *
     * @return array<string, array<int, string|array|\Closure>>
     */
    protected function discoveredEvents(): array
    {
        return [];
    }
}
