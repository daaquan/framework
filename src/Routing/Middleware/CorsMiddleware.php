<?php

namespace Phare\Routing\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\AfterMiddleware;

class CorsMiddleware extends MiddlewareContract implements AfterMiddleware
{
    public function handle(RequestInterface $request, ResponseInterface $response)
    {
        $response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization')
            ->setHeader('Access-Control-Allow-Credentials', 'true')->setHeader('Access-Control-Max-Age', '86400');
    }
}
