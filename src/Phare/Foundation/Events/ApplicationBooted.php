<?php

namespace Phare\Foundation\Events;

use Phare\Contracts\Foundation\Application;
use Phare\Events\Event;

class ApplicationBooted extends Event
{
    public function __construct(public Application $app) {}
}
