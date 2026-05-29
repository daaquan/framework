<?php

namespace Phare\Providers;

use Phalcon\Events\Manager;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Mvc\Model\Manager as ModelManager;
use Phalcon\Mvc\Model\MetaData\Memory;
use Phare\Eloquent\Builder;
use Phare\Support\ServiceProvider;

class ModelProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('modelsManager', function () use ($app) {
            foreach ($app['config']->path('app.phalcon.orm') ?? [] as $key => $value) {
                ini_set("phalcon.orm.$key", $value);
            }

            $modelManager = new ModelManager();
            $modelManager->setEventsManager(new Manager());

            return $modelManager;
        });

        $app->singleton('modelsMetadata', function () {
            return new Memory();
        });

        $app->singleton(Criteria::class, function () {
            return new Builder();
        });
    }
}
