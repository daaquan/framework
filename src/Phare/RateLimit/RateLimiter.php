<?php

namespace Phare\RateLimit;

use Phare\Contracts\Foundation\Application;

class RateLimiter
{
    protected Application $app;
    protected array $limiters = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function for(string $name, \Closure $callback): static
    {
        $this->limiters[$name] = $callback;
        return $this;
    }

    public function attempt(string $key, int $maxAttempts, int $decayMinutes = 1, \Closure $callback = null): mixed
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            throw new TooManyRequestsException('Too many attempts', $this->availableIn($key));
        }

        $result = $callback ? $callback() : true;

        $this->hit($key, $decayMinutes * 60);

        return $result;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->hasKey($key . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $cache = $this->getCache();
        
        $cache->add($key . ':timer', $this->availableAt($decaySeconds), $decaySeconds);

        $added = $cache->add($key, 0, $decaySeconds);

        $hits = (int) $cache->increment($key);

        if (!$added && $hits == 1) {
            $cache->put($key, 1, $decaySeconds);
        }

        return $hits;
    }

    public function attempts(string $key): int
    {
        return (int) $this->getCache()->get($key, 0);
    }

    public function resetAttempts(string $key): bool
    {
        return $this->getCache()->forget($key);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return $maxAttempts - $attempts;
    }

    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return $this->remaining($key, $maxAttempts);
    }

    public function clear(string $key): void
    {
        $cache = $this->getCache();
        $cache->forget($key);
        $cache->forget($key . ':timer');
    }

    public function availableIn(string $key): int
    {
        $cache = $this->getCache();
        return max(0, $cache->get($key . ':timer') - $this->currentTime());
    }

    public function cleanRateLimiterKey(string $key): string
    {
        return preg_replace('/&([a-z])[a-z]+;/i', '$1', htmlentities($key));
    }

    protected function hasKey(string $key): bool
    {
        return $this->getCache()->has($key);
    }

    protected function availableAt(int $seconds): int
    {
        return $this->currentTime() + $seconds;
    }

    protected function currentTime(): int
    {
        return time();
    }

    protected function getCache()
    {
        return $this->app->make('cache');
    }

    public function limiter(string $name): ?\Closure
    {
        return $this->limiters[$name] ?? null;
    }

    public function limit(int $maxAttempts): Limit
    {
        return new Limit($maxAttempts);
    }
}