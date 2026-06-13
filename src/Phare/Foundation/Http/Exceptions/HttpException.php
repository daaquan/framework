<?php

namespace Phare\Foundation\Http\Exceptions;

class HttpException extends \RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        protected int $statusCode = 400,
        string $message = '',
        protected array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
