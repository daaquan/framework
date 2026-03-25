<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;
use Sqids\Sqids;

class SqidsProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if (!extension_loaded('sqids')) {
            return;
        }

        $app->singleton('sqids', function () {
            return new Sqids(Sqids::DEFAULT_ALPHABET, 10);
        });
    }
}
