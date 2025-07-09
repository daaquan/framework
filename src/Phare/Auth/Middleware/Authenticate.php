<?php

namespace Phare\Auth\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;
use Phare\Foundation\Micro;
use Phare\Http\Response;
use Phare\Support\Facades\Auth;

/**
 * Authentication middleware
 */
class Authenticate extends MiddlewareContract implements BeforeMiddleware
{
    public function __construct(private Application $app) {}

    public function handle(RequestInterface $request, ResponseInterface $response)
    {
        if (Auth::check()) {
            return true;
        }

        $this->app->stop();

        $response->setStatusCode(Response::STATUS_UNAUTHORIZED, 'Unauthorized');

        if ($this->app instanceof Micro) {
            $response->send();
        } else {
            $response->redirect(route('login'));
        }
    }
}
