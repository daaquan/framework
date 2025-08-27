<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\QueueInterface;
use Phare\Queue\SyncQueue;

class SyncConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): QueueInterface
    {
        return new SyncQueue();
    }
}