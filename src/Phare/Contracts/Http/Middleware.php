<?php

namespace Phare\Contracts\Http;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

interface Middleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface;
}
