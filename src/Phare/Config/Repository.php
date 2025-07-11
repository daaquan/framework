<?php

namespace Phare\Config;

use Phalcon\Config\Config;

/**
 * Simple configuration repository extending Phalcon's Config class.
 * Adds a convenient get method that proxies to Config::path.
 */
class Repository extends Config
{
}
