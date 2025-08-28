<?php

namespace Phare\Events;

abstract class Event
{
    public function getName(): string
    {
        return static::class;
    }
}
