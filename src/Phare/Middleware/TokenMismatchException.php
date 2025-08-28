<?php

namespace Phare\Middleware;

class TokenMismatchException extends \Exception
{
    protected $message = 'CSRF token mismatch.';

    protected $code = 419;
}
