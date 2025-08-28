<?php

namespace Phare\Console\Scheduling;

use Phare\Console\Command;

class ScheduleRunCommand extends Command
{
    protected string $name = 'schedule:run';
    protected string $description = 'Run the scheduled commands';

    public function handle(): int
    {
        $schedule = $this->app['schedule'];
        $dueEvents = $schedule->dueEvents();

        if (empty($dueEvents)) {
            $this->info('No scheduled commands are ready to run.');
            return 0;
        }

        $this->info('Running scheduled commands...');

        $results = $schedule->run();
        
        foreach ($results as $result) {
            $event = $result['event'];
            $command = $event->getCommand();
            
            if ($result['success']) {
                $this->info("✓ {$command}");
                if (!empty($result['output'])) {
                    $this->line("  Output: " . trim($result['output']));
                }
            } else {
                $this->error("✗ {$command}");
                $this->line("  Error: " . trim($result['output']));
            }
        }

        $successCount = count(array_filter($results, fn($r) => $result['success']));
        $totalCount = count($results);

        $this->info("Completed {$successCount}/{$totalCount} scheduled commands.");

        return $successCount === $totalCount ? 0 : 1;
    }
}