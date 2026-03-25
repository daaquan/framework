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
        $domain = parse_url($request->getHeader('referer'), PHP_URL_HOST) ??
                  parse_url($request->getHeader('origin'), PHP_URL_HOST);

        if (is_null($domain)) {
            return false;
        }

        $statefulDomains = config('sanctum.stateful', []);

        foreach ($statefulDomains as $statefulDomain) {
            if ($domain === $statefulDomain || str_ends_with($domain, '.' . $statefulDomain)) {
                return true;
            }
        }

        return false;
    }

    protected function configureSecureCookieSession(): void
    {
        config([
            'session.same_site' => 'lax',
            'session.secure' => request()->isSecure(),
        ]);
    }
}
