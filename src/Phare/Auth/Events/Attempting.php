<?php

namespace Phare\Auth\Events;

class Attempting
{
    public function __construct(
        public array $credentials,
    ) {}
}
