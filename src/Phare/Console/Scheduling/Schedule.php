<?php

namespace Phare\Console\Scheduling;

class Schedule
{
    protected array $events = [];

    protected string $timezone = 'UTC';

    /**
     * Add a new command event to the schedule.
     */
    public function command(string $command, array $parameters = []): Event
    {
        return $this->exec(
            $this->buildCommand($command, $parameters)
        );
    }

    /**
     * Add a new job event to the schedule.
     */
    public function job(string|object $job, ?string $queue = null): Event
    {
        $event = new CallbackEvent($this->timezone, function () use ($job, $queue) {
            if (is_string($job)) {
                $job = new $job();
            }

            if (function_exists('dispatch')) {
                dispatch($job, $queue);
            }
        });

        return $this->events[] = $event;
    }

    /**
     * Add a new callback event to the schedule.
     */
    public function call(callable $callback, array $parameters = []): Event
    {
        $event = new CallbackEvent($this->timezone, function () use ($callback, $parameters) {
            return $callback(...$parameters);
        });

        return $this->events[] = $event;
    }

    /**
     * Add a new executable command to the schedule.
     */
    public function exec(string $command, array $parameters = []): Event
    {
        if (count($parameters)) {
            $command .= ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        }

        $event = new Event($this->timezone, $command);

        return $this->events[] = $event;
    }

    /**
     * Build the command string.
     */
    protected function buildCommand(string $command, array $parameters): string
    {
        $binary = defined('ARTISAN_BINARY') ? ARTISAN_BINARY : 'php artisan';

        $command = $binary . ' ' . $command;

        if (count($parameters)) {
            $command .= ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        }

        return $command;
    }

    /**
     * Get all of the events on the schedule.
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Get all of the events on the schedule that are due.
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, function (Event $event) {
            return $event->isDue();
        });
    }

    /**
     * Set the timezone for the schedule.
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        foreach ($this->events as $event) {
            $event->timezone($timezone);
        }

        return $this;
    }

    /**
     * Get the timezone for the schedule.
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * Clear all scheduled events.
     */
    public function clear(): static
    {
        $this->events = [];

        return $this;
    }

    /**
     * Run all due events.
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->dueEvents() as $event) {
            try {
                $results[] = [
                    'event' => $event,
                    'output' => $event->run(),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'event' => $event,
                    'output' => $e->getMessage(),
                    'success' => false,
                    'exception' => $e,
                ];
            }
        }

        return $results;
    }
}
