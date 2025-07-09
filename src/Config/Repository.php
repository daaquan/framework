<?php

namespace Phare\Config;

use Phalcon\Config\Config;

/**
 * Simple configuration repository extending Phalcon's Config class.
 * Adds a convenient get method that proxies to Config::path.
 */
class Repository extends Config
{
    /**
     * Retrieve an item from the configuration using "dot" notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->path($key, $default);
    }
}
