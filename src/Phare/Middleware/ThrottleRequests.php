<?php

namespace Phare\Middleware;

use Phare\Contracts\Foundation\Application;
use Phare\RateLimit\RateLimiter;
use Phare\RateLimit\TooManyRequestsException;
use Phalcon\Http\Request;
use Phalcon\Http\Response;

class ThrottleRequests
{
    protected Application $app;
    protected RateLimiter $limiter;

    public function __construct(Application $app, RateLimiter $limiter)
    {
        $this->app = $app;
        $this->limiter = $limiter;
    }

    public function handle(Request $request, \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, string $prefix = ''): Response
    {
        if (is_string($maxAttempts) && $this->limiter->limiter($maxAttempts)) {
            return $this->handleRequestUsingNamedLimiter($request, $next, $maxAttempts, $prefix);
        }

        return $this->handleRequest(
            $request,
            $next,
            [
                (object) [
                    'key' => $prefix . $this->resolveRequestSignature($request),
                    'maxAttempts' => $this->resolveMaxAttempts($request, $maxAttempts),
                    'decayMinutes' => $decayMinutes,
                    'responseCallback' => null,
                ]
            ]
        );
    }

    protected function handleRequestUsingNamedLimiter(Request $request, \Closure $next, string $limiterName, string $prefix): Response
    {
        $limiterCallback = $this->limiter->limiter($limiterName);

        if (!$limiterCallback) {
            throw new \RuntimeException("Rate limiter [{$limiterName}] is not defined.");
        }

        $limits = call_user_func($limiterCallback, $request);

        if (!is_array($limits)) {
            $limits = [$limits];
        }

        foreach ($limits as $limit) {
            if ($limit->key) {
                $limit->key = $prefix . $limit->key;
            } else {
                $limit->key = $prefix . $this->resolveRequestSignature($request);
            }
        }

        return $this->handleRequest($request, $next, $limits);
    }

    protected function handleRequest(Request $request, \Closure $next, array $limits): Response
    {
        foreach ($limits as $limit) {
            if ($this->limiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
                throw new TooManyRequestsException('Too many attempts', $this->getTimeUntilNextRetry($limit->key));
            }

            $this->limiter->hit($limit->key, $limit->decayMinutes * 60);
        }

        $response = $next($request);

        foreach ($limits as $limit) {
            $response = $this->addHeaders(
                $response,
                $limit->maxAttempts,
                $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
            );
        }

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->get('user')) {
            return sha1($user);
        }

        return sha1($request->getClientAddress() . '|' . $request->getURI());
    }

    protected function resolveMaxAttempts(Request $request, int $maxAttempts): int
    {
        if ($user = $request->get('authenticated_user')) {
            return $user['rate_limit'] ?? $maxAttempts;
        }

        return $maxAttempts;
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts, int $retryAfter = null): int
    {
        return is_null($retryAfter) ? $this->limiter->remaining($key, $maxAttempts) : 0;
    }

    protected function getTimeUntilNextRetry(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter = null): Response
    {
        $response->setHeader('X-RateLimit-Limit', $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', max(0, $remainingAttempts));

        if (!is_null($retryAfter)) {
            $response->setHeader('Retry-After', $retryAfter);
            $response->setHeader('X-RateLimit-Reset', time() + $retryAfter);
        }

        return $response;
    }
}