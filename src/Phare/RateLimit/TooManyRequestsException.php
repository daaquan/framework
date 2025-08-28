<?php

namespace Phare\RateLimit;

class TooManyRequestsException extends \RuntimeException
{
    protected int $retryAfter;

    public function __construct(string $message = 'Too Many Requests', int $retryAfter = 60, ?\Throwable $previous = null)
    {
        parent::__construct($message, 429, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
