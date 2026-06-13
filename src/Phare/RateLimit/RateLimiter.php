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

    public function attempt(string $key, int $maxAttempts, int $decayMinutes = 1, ?\Closure $callback = null): mixed
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
        // FAIL CLOSED: a live counter at or over the limit stays limited no
        // matter what happened to the companion :timer key. Eviction of the
        // timer must never reset the counter and re-open the gate. The counter
        // is the source of truth and decays on its own TTL.
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $cache = $this->getCache();
        $key = $this->cleanRateLimiterKey($key);

        // Store the reset timestamp atomically alongside the counter so that
        // availableIn() can report a retry window. The counter itself carries
        // the same TTL, so even if the timer is evicted the counter still gates.
        $cache->add($key . ':timer', $this->availableAt($decaySeconds), $decaySeconds);

        $added = $cache->add($key, 0, $decaySeconds);

        $hits = (int)$cache->increment($key);

        if (!$added && $hits == 1) {
            $cache->put($key, 1, $decaySeconds);
        }

        return $hits;
    }

    public function attempts(string $key): int
    {
        return (int)$this->getCache()->get($this->cleanRateLimiterKey($key), 0);
    }

    public function resetAttempts(string $key): bool
    {
        return $this->getCache()->forget($this->cleanRateLimiterKey($key));
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
        $key = $this->cleanRateLimiterKey($key);
        $cache->forget($key);
        $cache->forget($key . ':timer');
    }

    public function availableIn(string $key): int
    {
        $cache = $this->getCache();

        return max(0, (int)$cache->get($this->cleanRateLimiterKey($key) . ':timer') - $this->currentTime());
    }

    /**
     * Normalize an arbitrary rate-limit signature into a safe, collision-free
     * cache key. Hashing the raw signature avoids the key corruption and
     * collisions of HTML-entity stripping and yields a fixed character set that
     * is safe for every cache backend. Idempotent: hashing an already-hashed
     * key is harmless because every call here normalizes consistently.
     */
    public function cleanRateLimiterKey(string $key): string
    {
        return sha1($key);
    }

    protected function hasKey(string $key): bool
    {
        return $this->getCache()->has($this->cleanRateLimiterKey($key));
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
