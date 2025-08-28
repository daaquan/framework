<?php

namespace Phare\Broadcasting;

class PresenceChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('presence-' . $name);
    }
}
