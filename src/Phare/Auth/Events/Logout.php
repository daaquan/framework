<?php

namespace Phare\Auth\Events;

use Phare\Contracts\Auth\Authenticatable;

class Logout
{
    public function __construct(
        public ?Authenticatable $user,
    ) {}
}
