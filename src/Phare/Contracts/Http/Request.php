<?php

namespace Phare\Contracts\Http;

use Phalcon\Http\RequestInterface;

interface Request extends RequestInterface
{
    public function all();

    public function input($name, $default = null);
}
