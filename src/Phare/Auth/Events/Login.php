<?php

namespace Phare\Auth\Events;

use Phare\Contracts\Auth\Authenticatable;

class Login
{
    public function __construct(
        public Authenticatable $user,
    ) {}
}
