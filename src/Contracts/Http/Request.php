<?php

namespace Phare\Contracts\Http;

interface Request extends \Phalcon\Http\RequestInterface
{
    public function all();

    public function input($name, $default = null);
}
