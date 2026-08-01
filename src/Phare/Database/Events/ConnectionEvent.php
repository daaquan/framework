<?php

namespace Phare\Database\Events;

class ConnectionEvent
{
    public function __construct(public string $connectionName) {}
}
