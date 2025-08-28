<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\QueueInterface;
use Phare\Queue\DatabaseQueue;

class DatabaseConnector implements ConnectorInterface
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
        return new DatabaseQueue(array_merge($this->config, $config));
    }
}