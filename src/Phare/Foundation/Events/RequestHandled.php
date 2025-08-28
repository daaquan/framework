<?php

namespace Phare\Foundation\Events;

use Phare\Contracts\Http\Request;
use Phare\Contracts\Http\Response;
use Phare\Events\Event;

class RequestHandled extends Event
{
    public function __construct(
        public Request $request,
        public Response $response
    ) {}
}
