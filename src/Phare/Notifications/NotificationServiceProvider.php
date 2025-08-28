<?php

namespace Phare\Notifications;

use Phare\Notifications\Channels\ChannelManager;
use Phare\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('notification.channel', function ($app) {
            $config = $app['config']['notification'] ?? [];

            return new ChannelManager($config);
        });

        $this->app->singleton('notification', function ($app) {
            $channelManager = $app['notification.channel'];
            $events = $app['events'] ?? null;

            return new NotificationManager($channelManager, $events);
        });

        $this->app->bind(NotificationManager::class, function ($app) {
            return $app['notification'];
        });

        $this->app->bind(ChannelManager::class, function ($app) {
            return $app['notification.channel'];
        });
    }

    public function boot(): void
    {
        // Register notification helper functions
        if (!function_exists('notify')) {
            function notify(mixed $notifiable, Notification $notification): void
            {
                app('notification')->send($notifiable, $notification);
            }
        }

        if (!function_exists('notification')) {
            function notification(): NotificationManager
            {
                return app('notification');
            }
        }
    }
}
