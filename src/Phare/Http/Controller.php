<?php

namespace Phare\Http;

use Phalcon\Mvc\Controller as BaseController;

/**
 * Base controller providing convenient access to services.
 */
abstract class Controller extends BaseController
{
    /**
     * Retrieve a configuration value using dot notation.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->di->getShared('config')->get($key, $default);
    }

    /**
     * Return the cache repository instance.
     */
    protected function cache(): \Phare\Foundation\Cache
    {
        return $this->di->getShared('cache');
    }
}
