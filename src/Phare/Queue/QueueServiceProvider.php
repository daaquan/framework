<?php

namespace Phare\Queue;

use Phare\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('queue', function ($app) {
            $config = $app['config']['queue'] ?? [
                'default' => 'sync',
                'connections' => [
                    'sync' => ['driver' => 'sync'],
                    'database' => ['driver' => 'database', 'table' => 'jobs'],
                    'redis' => ['driver' => 'redis'],
                ]
            ];

            return new QueueManager($config);
        });

        $this->app->bind(QueueManager::class, function ($app) {
            return $app['queue'];
        });

        $this->app->bind(QueueInterface::class, function ($app) {
            return $app['queue']->connection();
        });
    }

    public function boot(): void
    {
        // Register queue helper functions
        if (!function_exists('queue')) {
            function queue(?string $connection = null): QueueInterface {
                return app('queue')->connection($connection);
            }
        }

        if (!function_exists('dispatch')) {
            function dispatch(Job $job, ?string $queue = null, ?string $connection = null): string {
                return app('queue')->push($job, $queue, $connection);
            }
        }

        if (!function_exists('dispatch_after')) {
            function dispatch_after(Job $job, int $delay, ?string $queue = null, ?string $connection = null): string {
                return app('queue')->later($job, $delay, $queue, $connection);
            }
        }
    }
}