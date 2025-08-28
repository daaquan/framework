<?php

namespace Phare\Broadcasting;

class Channel
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
