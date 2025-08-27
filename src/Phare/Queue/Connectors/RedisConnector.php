<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\QueueInterface;
use Phare\Queue\RedisQueue;

class RedisConnector implements ConnectorInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Establish a queue connection.
     */
    public function connect(array $config): QueueInterface
    {
        return new RedisQueue(array_merge($this->config, $config));
    }
}