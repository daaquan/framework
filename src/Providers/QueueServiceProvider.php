<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Phare\Foundation\AbstractApplication as Application;

class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
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
