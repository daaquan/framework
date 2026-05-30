<?php

namespace Phare\Auth\Sanctum\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\Middleware;

class EnsureFrontendRequestsAreStateful implements Middleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if ($this->fromFrontend($request)) {
            $this->configureSecureCookieSession();
        }

        return $next($request);
    }

    protected function fromFrontend(RequestInterface $request): bool
    {
        $domain = $this->hostFromHeader($request->getHeader('referer'))
            ?? $this->hostFromHeader($request->getHeader('origin'));

        if ($domain === null) {
            return false;
        }

        $statefulDomains = $this->statefulDomains();

        foreach ($statefulDomains as $statefulDomain) {
            $statefulDomain = strtolower(trim((string)$statefulDomain));

            if ($statefulDomain === '') {
                continue;
            }

            if ($domain === $statefulDomain || str_ends_with($domain, '.' . $statefulDomain)) {
                return true;
            }
        }

        return false;
    }

    protected function hostFromHeader(string $header): ?string
    {
        if ($header === '') {
            return null;
        }

        $host = parse_url($header, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    protected function statefulDomains(): array
    {
        return config('sanctum.stateful', []);
    }

    protected function configureSecureCookieSession(): void
    {
        config([
            'session.same_site' => 'lax',
            'session.secure' => request()->isSecure(),
        ]);
    }
}
