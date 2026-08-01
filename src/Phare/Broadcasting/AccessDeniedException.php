<?php

namespace Phare\Broadcasting;

/**
 * Thrown when a channel authorisation request is rejected.
 *
 * Broadcasting used to throw Symfony's AccessDeniedHttpException, but
 * symfony/http-kernel is not a dependency of this package, so the denial path
 * fataled with "class not found" instead of returning 403.
 */
class AccessDeniedException extends \RuntimeException
{
    public function __construct(string $message = 'Forbidden', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
