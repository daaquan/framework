<?php

namespace Phare\Middleware;

use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phare\Contracts\Foundation\Application;
use Phare\Security\Csrf;

class VerifyCsrfToken
{
    protected Application $app;

    protected Csrf $csrf;

    protected array $except = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->csrf = $app->make(Csrf::class);
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if ($this->tokensMatch($request)) {
            return $next($request);
        }

        throw new TokenMismatchException('CSRF token mismatch.');
    }

    protected function shouldSkip(Request $request): bool
    {
        if ($this->isReading($request)) {
            return true;
        }

        foreach ($this->except as $except) {
            if ($this->inExceptArray($request, $except)) {
                return true;
            }
        }

        return false;
    }

    protected function isReading(Request $request): bool
    {
        return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS']);
    }

    protected function tokensMatch(Request $request): bool
    {
        $token = $this->getTokenFromRequest($request);

        return is_string($token) && $this->csrf->verifyToken($token);
    }

    protected function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->get('_token', 'string');

        if (!$token && $header = $request->getHeader('X-CSRF-TOKEN')) {
            $token = $header;
        }

        if (!$token && $header = $request->getHeader('X-XSRF-TOKEN')) {
            $token = $header;
        }

        return $token;
    }

    protected function inExceptArray(Request $request, string $except): bool
    {
        if ($except !== '/') {
            $except = trim($except, '/');
        }

        $uri = $request->getURI();

        if ($except === $uri) {
            return true;
        }

        if (str_contains($except, '*')) {
            $pattern = preg_quote($except, '/');
            $pattern = str_replace('\*', '.*', $pattern);

            return preg_match('/^' . $pattern . '$/', $uri) === 1;
        }

        return false;
    }

    public function addExcept(array $routes): static
    {
        $this->except = array_merge($this->except, $routes);

        return $this;
    }
}
