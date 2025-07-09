<?php

namespace Phare\Contracts\Http;

use Phalcon\Di\Di;
use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use Phare\Foundation\Http\Concerns\AfterMiddleware;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;

abstract class MiddlewareContract extends Di implements MiddlewareInterface
{
    /**
     * Executed when the MVC application is invoked.
     *
     * Registered to the application:beforeHandleRequest event before the request is handled.
     */
    protected function beforeHandleRequest(Event $event, Application $app)
    {
        if ($this instanceof BeforeMiddleware) {
            $this->handle($app->request, $app->response);
        }
    }

    /**
     * Executed when the MVC application is invoked.
     *
     * Registered to the application:beforeSendResponse event before the response is sent.
     */
    protected function beforeSendResponse(Event $event, Application $app)
    {
        if ($this instanceof AfterMiddleware) {
            $this->handle($app->request, $app->response);
        }
    }

    /**
     * Executed when the Micro application is invoked.
     */
    public function call(Micro $app)
    {
        $this->handle($app->request, $app->response);
    }

    /**
     * Execute the middleware logic.
     */
    abstract public function handle(RequestInterface $request, ResponseInterface $response);
}
