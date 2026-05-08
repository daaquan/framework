<?php

declare(strict_types=1);

namespace Phare\Auth;

class AuthenticationException extends \RuntimeException
{
    public function __construct(string $message = 'Unauthenticated.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
