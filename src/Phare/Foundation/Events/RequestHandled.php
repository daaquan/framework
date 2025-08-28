<?php

namespace Phare\Foundation\Events;

use Phare\Events\Event;
use Phare\Contracts\Http\Request;
use Phare\Contracts\Http\Response;

class RequestHandled extends Event
{
    public function __construct(
        public Request $request, 
        public Response $response
    ) {
    }
}