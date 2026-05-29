<?php

namespace Phare\Providers;

use Phare\Support\ServiceProvider;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('queue', function () {
            $connection = config('queue.default', 'beanstalkd');
            $config = config("queue.connections.$connection");
            if (!$config) {
                throw new \RuntimeException('Queue connection is not configured.');
            }

            // Queue a deploy Job
            $queueName = new TubeName($config['queue']);
            $beanstalk = Pheanstalk::create($config['host'], (int)$config['port']);
            $beanstalk->useTube($queueName);

            return $beanstalk;
        });
    }
}
