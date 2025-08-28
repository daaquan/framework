<?php

namespace Phare\Queue\Connectors;

use Phare\Queue\QueueInterface;

interface ConnectorInterface
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): QueueInterface;
}
