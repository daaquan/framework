<?php

namespace Phare\Auth\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;
use Phare\Foundation\Http\ResponseStatusCode;
use Phare\Foundation\Micro;
use Phare\Support\Facades\Auth;

class Authenticate extends MiddlewareContract implements BeforeMiddleware
{
    public function __construct(private Application $app) {}

    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if (Auth::check()) {
            return $next($request);
        }

        $this->app->stop();

        $response = $next($request);
        $response->setStatusCode(ResponseStatusCode::BAD_UNAUTHORIZED->value, ResponseStatusCode::BAD_UNAUTHORIZED->message());

        if ($this->app instanceof Micro) {
            $response->send();
        } else {
            $response->redirect(route('login'));
        }

        return $response;
    }
}
