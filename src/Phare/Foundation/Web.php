<?php

declare(strict_types=1);

namespace Phare\Foundation;

use Phalcon\Mvc\Application as App;
use Phare\Foundation\Http\ResponseStatusCode;

class Web extends AbstractApplication
{
    protected function createApplication()
    {
        return (new App($this))
            ->useImplicitView(false);
    }

    public function handle($uri)
    {
        $this['response']
            ->setStatusCode(ResponseStatusCode::OK->value);

        return $this->app->handle($uri);
    }

    public function mount($group)
    {
        $this['router']->mount($group);
    }

    public function middleware($abstract)
    {
        $middleware = $this->make($abstract);

        $eventsManager = $this['eventsManager'];
        $eventsManager->attach('application', $middleware);

        $this->app->setEventsManager($eventsManager);
    }

    public function terminate() {}
}
