<?php

namespace Phare\Middleware;

use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\Middleware;
use Phare\RateLimit\RateLimiter;
use Phare\RateLimit\TooManyRequestsException;

class ThrottleRequests implements Middleware
{
    protected Application $app;

    protected RateLimiter $limiter;

    public function __construct(Application $app, RateLimiter $limiter)
    {
        $this->app = $app;
        $this->limiter = $limiter;
    }

    public function handle(RequestInterface $request, \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, string $prefix = ''): ResponseInterface
    {
        if (is_string($maxAttempts) && $this->limiter->limiter($maxAttempts)) {
            return $this->handleRequestUsingNamedLimiter($request, $next, $maxAttempts, $prefix);
        }

        return $this->handleRequest(
            $request,
            $next,
            [
                (object)[
                    'key' => $prefix . $this->resolveRequestSignature($request),
                    'maxAttempts' => $this->resolveMaxAttempts($request, $maxAttempts),
                    'decayMinutes' => $decayMinutes,
                    'responseCallback' => null,
                ],
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
        // Derive the throttle bucket from the SERVER-SIDE authenticated principal,
        // never from client-supplied request parameters (which a client could
        // rotate to escape its own bucket). Fall back to the client IP + URI when
        // the request is unauthenticated.
        $id = $this->resolveAuthenticatedId();

        if ($id !== null) {
            return sha1('user|' . $id);
        }

        return sha1($request->getClientAddress() . '|' . $request->getURI());
    }

    protected function resolveMaxAttempts(Request $request, int $maxAttempts): int
    {
        // Limits must be authoritative and server-derived. We never read a limit
        // from request input — a client could otherwise lift its own throttle.
        return $maxAttempts;
    }

    /**
     * Resolve the authenticated user's identifier from the auth manager in the
     * container. Returns null when no auth manager is bound or no user is logged in.
     */
    protected function resolveAuthenticatedId(): int|string|null
    {
        try {
            if (!$this->app->has('auth')) {
                return null;
            }

            $auth = $this->app->make('auth');

            if (is_object($auth) && method_exists($auth, 'id')) {
                return $auth->id();
            }
        } catch (\Throwable) {
            // A misconfigured or unavailable auth manager must not weaken
            // throttling — fall back to the IP-based signature.
        }

        return null;
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts, ?int $retryAfter = null): int
    {
        return is_null($retryAfter) ? $this->limiter->remaining($key, $maxAttempts) : 0;
    }

    protected function getTimeUntilNextRetry(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
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
