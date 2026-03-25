<?php

namespace Phare\Auth\Events;

use Phare\Contracts\Auth\Authenticatable;

class Failed
{
    public function __construct(
        public ?Authenticatable $user,
        public array $credentials,
    ) {}
}
