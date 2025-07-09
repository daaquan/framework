<?php

namespace Phare\Contracts\Session;

use Phalcon\Session\ManagerInterface;

interface Session extends ManagerInterface
{
    public function pull($key, $default = null);

    public function put($key, $value);

    public function add($key, $value);

    public function forget($key);

    public function clear();

    public function replace(array $attributes);
}
