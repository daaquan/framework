<?php

namespace Phare\Contracts\Http;

use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use Phare\Foundation\Http\Concerns\AfterMiddleware;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;

abstract class MiddlewareContract implements Middleware, MiddlewareInterface
{
    protected function beforeHandleRequest(Event $event, Application $app)
    {
        if ($this instanceof BeforeMiddleware) {
            return $this->handle($app->request, fn () => $app->response);
        }
    }

    protected function beforeSendResponse(Event $event, Application $app)
    {
        if ($this instanceof AfterMiddleware) {
            return $this->handle($app->request, fn () => $app->response);
        }
    }

    public function call(Micro $app)
    {
        return $this->handle($app->request, fn () => $app->response);
    }

    abstract public function handle(RequestInterface $request, \Closure $next): ResponseInterface;
}
