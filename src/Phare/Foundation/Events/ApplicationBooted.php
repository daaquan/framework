<?php

namespace Phare\Foundation\Events;

use Phare\Events\Event;
use Phare\Contracts\Foundation\Application;

class ApplicationBooted extends Event
{
    public function __construct(public Application $app)
    {
    }
}