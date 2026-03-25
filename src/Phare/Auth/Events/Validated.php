<?php

namespace Phare\Auth\Events;

use Phare\Contracts\Auth\Authenticatable;

class Validated
{
    public function __construct(
        public Authenticatable $user,
        public array $credentials,
    ) {}
}
