<?php

namespace Phare\Session\Adapter;

use Phalcon\Session\Adapter\AbstractAdapter;
use Phalcon\Storage\Adapter\AdapterInterface;

class RedisCluster extends AbstractAdapter
{
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }
}
