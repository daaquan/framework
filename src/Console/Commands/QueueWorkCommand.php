<?php

declare(strict_types=1);

namespace Phare\Console\Commands;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'queue:work',
    description: 'Watch and reserve beanstalkd queue.',
)]
class QueueWorkCommand extends Command
{
    private const int DEFAULT_RETRY_LIMIT = 3;

    protected function configure(): void
    {
        $this->addOption(
            'tries',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of times to retry a job',
            self::DEFAULT_RETRY_LIMIT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = config('queue.default', 'beanstalkd');
        $config = config("queue.connections.$connection");
        if (!$config) {
            $output->writeln('<error>Queue connection is not configured.</error>');

            return Command::FAILURE;
        }

        try {
            $pheanstalk = Pheanstalk::create($config['host'], (int)$config['port']);
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to connect to Beanstalkd: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $queueName = new TubeName($config['queue']);
        $retryLimit = (int)$input->getOption('tries');

        $output->writeln(
            "  <fg=white;bg=blue> INFO </> Processing jobs from the [<options=bold>{$config['queue']}</>] queue."
        );

        // Watching once is sufficient
        $pheanstalk->watch($queueName);

        while (true) {
            $reserved = $pheanstalk->reserve();
            $jobData = null;

            try {
                $jobData = $this->unserializeJobData($reserved->getData());
                $this->handleJob($jobData);

                // Call touch() for long-running jobs if necessary
                // $pheanstalk->touch($reserved);

                $pheanstalk->delete($reserved);

                $output->writeln(
                    sprintf(
                        '  <fg=gray>%s</> %s <fg=green>processed</>',
                        date('Y-m-d H:i:s'),
                        $this->getJobClassName($jobData)
                    )
                );
            } catch (\Throwable $e) {
                $this->handleJobFailure($pheanstalk, $reserved, $jobData, $retryLimit, $output, $e);
            }
        }
    }

    private function unserializeJobData(string $data): array
    {
        $jobData = unserialize($data, ['allowed_classes' => true]);
        if (!is_array($jobData) || !isset($jobData['closure'])) {
            throw new \RuntimeException('Invalid job data.');
        }

        return $jobData;
    }

    private function handleJob(array $jobData): void
    {
        // If a class instance is provided, call handle()
        if (is_object($jobData['closure']) && method_exists($jobData['closure'], 'handle')) {
            $jobData['closure']->handle();
        } else {
            throw new \RuntimeException('Job does not implement handle().');
        }
    }

    private function handleJobFailure(
        Pheanstalk $pheanstalk,
        $reserved,
        ?array $jobData,
        int $retryLimit,
        OutputInterface $output,
        \Throwable $e
    ): void {
        $retry = $jobData['retry'] ?? 0;
        $retry++;
        $jobClass = $jobData && isset($jobData['closure']) ? $this->getJobClassName($jobData) : 'UnknownJob';

        if ($retry > $retryLimit) {
            $pheanstalk->delete($reserved);
            $output->writeln("<error>Failed: {$jobClass} (deleted after max retries)</error>");
        } else {
            if ($jobData !== null) {
                $jobData['retry'] = $retry;
                $pheanstalk->release($reserved, Pheanstalk::DEFAULT_PRIORITY, 1);
            } else {
                // Delete the job if the data is corrupted
                $pheanstalk->delete($reserved);
            }
            $output->writeln("<error>Error: {$jobClass} retry {$retry}/{$retryLimit} - {$e->getMessage()}</error>");
        }
    }

    private function getJobClassName(array $jobData): string
    {
        if (is_object($jobData['closure'])) {
            return get_class($jobData['closure']);
        }

        return 'UnknownJob';
    }
}
