<?php

declare(strict_types=1);

namespace Phare\Foundation;

use Phalcon\Mvc\Micro as App;
use Phare\Foundation\Http\Concerns\AfterMiddleware;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;
use Phare\Foundation\Http\ResponseStatusCode;

class Micro extends AbstractApplication
{
    protected function createApplication()
    {
        return (new App($this))
            ->notFound($this->getNotFoundHandler());
    }

    public function handle($uri)
    {
        $response = $this['response']
            ->setStatusCode(ResponseStatusCode::OK->value);

        return $this->app->setResponseHandler(function () use ($response) {
            // Prevent response sending
            // @see https://github.com/phalcon/cphalcon/blob/v5.2.1/phalcon/Mvc/Micro.zep#L754
            return $response;
        })->handle($uri);
    }

    public function mount($router): void
    {
        $this->app->mount($router);
    }

    public function middleware($abstract)
    {
        $middleware = $this->make($abstract);

        $eventsManager = $this['eventsManager'];
        $eventsManager->attach('micro', $middleware);

        $this->app->setEventsManager($eventsManager);

        if ($middleware instanceof BeforeMiddleware) {
            $this->app->before($middleware);
        }
        if ($middleware instanceof AfterMiddleware) {
            $this->app->after($middleware);
        }
    }

    protected function setNotFoundHandler(\Closure $handler)
    {
        $this->notFoundHandler = $handler;
    }

    protected function getNotFoundHandler()
    {
        return $this->notFoundHandler ?? function () {
            $this->app->stop();

            $this->app['response']
                ->setStatusCode(404, 'Not Found')
                ->setContentType('text/html');

            return false;
        };
    }

    public function terminate()
    {
        $this->app->finish(function ($app) {
            // $app['log']?->close();
        });
    }
}
