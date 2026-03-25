<?php

namespace Phare\Auth\Events;

use Phare\Contracts\Auth\Authenticatable;

class Authenticated
{
    public function __construct(
        public Authenticatable $user,
    ) {}
}
