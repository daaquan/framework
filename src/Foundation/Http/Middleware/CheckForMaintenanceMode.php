<?php

namespace Phare\Foundation\Http\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;

class CheckForMaintenanceMode extends MiddlewareContract implements BeforeMiddleware
{
    public function __construct(private Application $app) {}

    public function handle(RequestInterface $request, ResponseInterface $response)
    {
        $whitelist = config('whitelist.ip');

        $ipAddress = $request->getClientAddress(true);

        if (!in_array($ipAddress, $whitelist?->toArray() ?? [], true)) {
            $this->app->stop();

            $response
                ->setStatusCode(403, 'Forbidden')
                ->setContentType('text/html')
                ->sendHeaders()
                ->send();
        }
    }
}
