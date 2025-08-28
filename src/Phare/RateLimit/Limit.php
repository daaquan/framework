<?php

namespace Phare\RateLimit;

class Limit
{
    public int $maxAttempts;
    public int $decayMinutes;
    public string $key;
    public ?\Closure $responseCallback = null;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public static function perMinute(int $maxAttempts): static
    {
        return new static($maxAttempts, 1);
    }

    public static function perMinutes(int $decayMinutes, int $maxAttempts): static
    {
        return new static($maxAttempts, $decayMinutes);
    }

    public static function perHour(int $maxAttempts): static
    {
        return new static($maxAttempts, 60);
    }

    public static function perDay(int $maxAttempts): static
    {
        return new static($maxAttempts, 1440);
    }

    public static function none(): static
    {
        return new static(PHP_INT_MAX);
    }

    public function by(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function response(\Closure $callback): static
    {
        $this->responseCallback = $callback;
        return $this;
    }
}