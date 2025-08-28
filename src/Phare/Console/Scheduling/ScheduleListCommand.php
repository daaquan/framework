<?php

namespace Phare\Console\Scheduling;

use Phare\Console\Command;

class ScheduleListCommand extends Command
{
    protected string $name = 'schedule:list';

    protected string $description = 'List all scheduled commands';

    public function handle(): int
    {
        $schedule = $this->app['schedule'];
        $events = $schedule->events();

        if (empty($events)) {
            $this->info('No scheduled commands found.');

            return 0;
        }

        $this->info('Scheduled Commands:');
        $this->line('');

        $rows = [];
        foreach ($events as $index => $event) {
            $rows[] = [
                $index + 1,
                $event->getExpression(),
                $event->getCommand(),
                $event->getTimezone(),
                $event->isDue() ? '✓' : '✗',
            ];
        }

        $this->table([
            '#', 'Cron', 'Command', 'Timezone', 'Due',
        ], $rows);

        return 0;
    }
}
