<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\DatabaseQueue;
use Phare\Queue\QueueInterface;

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
