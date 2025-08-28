<?php

namespace Phare\Console\Scheduling;

use Phare\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('schedule', function ($app) {
            $schedule = new Schedule();

            // Set timezone from config
            $timezone = $app['config']['app.timezone'] ?? 'UTC';
            $schedule->timezone($timezone);

            return $schedule;
        });

        $this->app->bind(Schedule::class, function ($app) {
            return $app['schedule'];
        });
    }

    public function boot(): void
    {
        // Register schedule helper functions
        if (!function_exists('schedule')) {
            function schedule(): Schedule
            {
                return app('schedule');
            }
        }

        // Register schedule:run command if console kernel is available
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScheduleRunCommand::class,
                ScheduleListCommand::class,
            ]);
        }
    }

    /**
     * Register commands with the application.
     */
    protected function commands(array $commands): void
    {
        // In a full implementation, this would register commands with the console kernel
        // For now, we'll make the commands available
        foreach ($commands as $command) {
            $this->app->bind($command);
        }
    }
}
