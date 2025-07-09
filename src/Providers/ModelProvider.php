<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Events\Manager;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Mvc\Model\Manager as ModelManager;
use Phalcon\Mvc\Model\MetaData\Memory;
use Phare\Eloquent\Builder;
use Phare\Foundation\AbstractApplication as Application;

class ModelProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
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
