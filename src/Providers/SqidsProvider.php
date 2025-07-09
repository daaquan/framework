<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class SqidsProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        if (!extension_loaded('sqids')) {
            throw new \RuntimeException('Sqids extension is not loaded.');
        }

        $app->singleton('sqids', function () {
            return new \Sqids\Sqids(\Sqids\Sqids::DEFAULT_ALPHABET, 10);
        });
    }
}
